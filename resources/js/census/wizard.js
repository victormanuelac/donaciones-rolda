import { queueCensusEntry } from './offline-db.js';
import { flushCensusQueue } from './sync.js';

const TOTAL_STEPS = 6;

function emptyMember() {
    return {
        full_name: '',
        document_type: '',
        document_number: '',
        relationship_to_head: '',
        sex: '',
        birthdate: '',
        is_household_head: false,
    };
}

function defaultState() {
    return {
        step: 1,
        totalSteps: TOTAL_STEPS,
        submitting: false,
        submitted: false,
        queuedOffline: false,
        declined: false,
        gpsStatus: 'idle', // idle | locating | ok | error
        errorMessage: '',

        family: {
            zone_id: '',
            zone_type: '',
            neighborhood: '',
            address: '',
            phone: '',
            route_code: '',
            latitude: null,
            longitude: null,
            gps_accuracy_meters: null,
            gps_captured_at: null,
            head_full_name: '',
            head_document_type: '',
            head_document_number: '',
            head_sex: '',
            head_birthdate: '',
            head_gender_identity: '',
            housing_damage_level: '',
            housing_inspection_mark: '',
            tenure_type: '',
            monthly_rent: null,
            water_access: '',
            water_source: '',
            electricity_access: '',
            sanitation_access: '',
            rooms_count: null,
        },

        census_entry: {
            surveyed_at: new Date().toISOString().slice(0, 16),
            surveyor_entity: 'donaciones_rolda',
            consent_given: null,
            consent_minors: 'no_aplica',
            consent_given_by_name: '',
            consent_relationship: '',
            total_people: 1,
            under_5_count: 0,
            over_60_count: 0,
            pregnant_lactating_count: 0,
            disability_count: 0,
            chronic_illness_count: 0,
            meals_yesterday: 3,
            rcsi_less_preferred: null,
            rcsi_borrow_food: null,
            rcsi_reduce_portion: null,
            rcsi_reduce_adult_consumption: null,
            rcsi_reduce_meals: null,
            injured: false,
            needs_urgent_medical_attention: false,
            lost_permanent_medication: false,
            sleeping_location: '',
            needs_temporary_shelter: '',
            environment_risks: [],
            access_passable: '',
            priority_needs: [],
            registered_in_rud: 'no_sabe',
            damage_verified: '',
            needs_structural_assessment: false,
        },

        members: [],
    };
}

export default function censusWizard() {
    return {
        ...defaultState(),

        get showRcsi() {
            return this.census_entry.meals_yesterday <= 2;
        },

        get rentRequired() {
            return this.family.tenure_type === 'arrendada';
        },

        acceptConsent(accepted) {
            this.census_entry.consent_given = accepted;
            this.declined = !accepted;
        },

        addMember() {
            this.members.push(emptyMember());
        },

        removeMember(index) {
            this.members.splice(index, 1);
        },

        next() {
            if (this.step < this.totalSteps) {
                this.step += 1;
            }
        },

        back() {
            if (this.step > 1) {
                this.step -= 1;
            }
        },

        captureGps() {
            if (!('geolocation' in navigator)) {
                this.gpsStatus = 'error';
                return;
            }

            this.gpsStatus = 'locating';

            navigator.geolocation.getCurrentPosition(
                (position) => {
                    this.family.latitude = position.coords.latitude;
                    this.family.longitude = position.coords.longitude;
                    this.family.gps_accuracy_meters = Math.round(position.coords.accuracy);
                    this.family.gps_captured_at = new Date().toISOString();
                    this.gpsStatus = 'ok';
                },
                () => {
                    // Sin señal GPS o permiso denegado: el encuestador ubica el pin
                    // manualmente en el mapa (ver setManualPin(), invocado desde Leaflet).
                    this.gpsStatus = 'error';
                },
                { enableHighAccuracy: true, timeout: 15000 }
            );
        },

        setManualPin(lat, lng) {
            this.family.latitude = lat;
            this.family.longitude = lng;
            this.family.gps_accuracy_meters = null;
            this.family.gps_captured_at = new Date().toISOString();
            this.gpsStatus = 'ok';
        },

        buildPayload() {
            const clientUuid = crypto.randomUUID();

            return {
                client_uuid: clientUuid,
                family: this.family,
                census_entry: this.census_entry,
                members: this.members,
            };
        },

        async submit() {
            this.submitting = true;
            this.errorMessage = '';

            const payload = this.buildPayload();

            try {
                const response = await fetch('/censo/sync', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                    },
                    body: JSON.stringify({ entries: [payload] }),
                });

                if (response.ok) {
                    const { results } = await response.json();

                    if (results[0]?.status === 'ok') {
                        this.submitted = true;
                    } else {
                        this.errorMessage = results[0]?.message ?? 'No se pudo guardar la captura.';
                    }
                } else if (response.status === 422) {
                    const body = await response.json();
                    this.errorMessage = Object.values(body.errors ?? {}).flat().join(' ') || 'Revisa los datos del formulario.';
                } else {
                    await this.queueOffline(payload.client_uuid, payload);
                }
            } catch {
                // Sin conexión: se guarda localmente y se sincroniza más tarde.
                await this.queueOffline(payload.client_uuid, payload);
            } finally {
                this.submitting = false;
            }
        },

        async queueOffline(clientUuid, payload) {
            await queueCensusEntry(clientUuid, payload);
            this.queuedOffline = true;
            this.submitted = true;
        },

        startNew() {
            Object.assign(this, defaultState());
        },

        async syncNow() {
            return flushCensusQueue();
        },
    };
}
