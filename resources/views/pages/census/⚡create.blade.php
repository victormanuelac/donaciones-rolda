<?php

use App\Models\GeographicZone;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Censo de hogares — Fase 1')] class extends Component {
    #[Computed]
    public function zones()
    {
        return GeographicZone::orderBy('name')->get(['id', 'name', 'zone_type']);
    }
}; ?>

<section class="w-full" x-data="censusWizard()" x-cloak>
    <flux:heading size="xl">{{ __('Censo de hogares — Fase 1 (triaje)') }}</flux:heading>
    <flux:subheading class="mb-6">
        {{ __('Cubre el hogar casa por casa en las primeras 72 horas. Funciona sin conexión: si no hay señal, la captura se guarda en este dispositivo y se sincroniza sola al recuperarla.') }}
    </flux:subheading>

    {{-- Estado final: enviado, en cola offline, o consentimiento negado --}}
    <div x-show="submitted" x-cloak class="card-brutal p-8 text-center">
        <template x-if="queuedOffline">
            <div>
                <flux:heading size="lg">{{ __('Guardado en este dispositivo') }}</flux:heading>
                <p class="text-muted mt-2">{{ __('No hay conexión ahora mismo. La captura se sincronizará automáticamente en cuanto vuelvas a tener señal.') }}</p>
            </div>
        </template>
        <template x-if="!queuedOffline">
            <div>
                <flux:heading size="lg">{{ __('Captura registrada') }}</flux:heading>
                <p class="text-muted mt-2">{{ __('El hogar quedó registrado correctamente.') }}</p>
            </div>
        </template>
        <flux:button class="mt-6" variant="primary" x-on:click="startNew()">{{ __('Registrar otro hogar') }}</flux:button>
    </div>

    <div x-show="declined && !submitted" x-cloak class="card-brutal p-8 text-center">
        <flux:heading size="lg">{{ __('Consentimiento no otorgado') }}</flux:heading>
        <p class="text-muted mt-2">{{ __('No se captura ningún dato personal. Solo se deja constancia de la visita en el registro físico de ruta.') }}</p>
        <flux:button class="mt-6" variant="primary" x-on:click="startNew()">{{ __('Volver al inicio') }}</flux:button>
    </div>

    {{-- Wizard --}}
    <div x-show="!submitted && !declined" x-cloak class="card-brutal p-6 md:p-8 space-y-6">
        <div class="flex items-center gap-2">
            <template x-for="n in totalSteps" :key="n">
                <div class="h-2 flex-1 rounded-full" :class="n <= step ? 'bg-accent' : 'bg-surface-2'"></div>
            </template>
        </div>
        <p class="text-sm text-muted" x-text="`{{ __('Paso') }} ${step} {{ __('de') }} ${totalSteps}`"></p>

        <flux:callout x-show="errorMessage" x-cloak variant="danger" x-text="errorMessage"></flux:callout>

        {{-- Paso 1: Consentimiento (Módulo B, bloqueante) --}}
        <template x-if="step === 1">
            <div class="space-y-4">
                <flux:heading size="lg">{{ __('Consentimiento informado') }}</flux:heading>
                <flux:callout variant="secondary">
                    {{ __('Donaciones Rolda, en apoyo a la Alcaldía de Roldanillo y al CMGRD, recolecta sus datos personales —incluidos datos sensibles como salud, pertenencia étnica y discapacidad— con la única finalidad de caracterizar su hogar, medir su nivel de afectación y priorizar la ayuda humanitaria de esta emergencia. Sus datos serán tratados de forma confidencial conforme a la Ley 1581 de 2012.') }}
                </flux:callout>

                <flux:field>
                    <flux:label>{{ __('¿Autoriza el tratamiento de sus datos personales para la atención de la emergencia?') }}</flux:label>
                    <div class="flex gap-3 mt-2">
                        <flux:button x-on:click="acceptConsent(true)" variant="primary" x-bind:class="census_entry.consent_given === true ? '' : 'opacity-50'">{{ __('Sí autoriza') }}</flux:button>
                        <flux:button x-on:click="acceptConsent(false)" variant="danger" x-bind:class="census_entry.consent_given === false ? '' : 'opacity-50'">{{ __('No autoriza') }}</flux:button>
                    </div>
                </flux:field>

                <template x-if="census_entry.consent_given === true">
                    <div class="space-y-4">
                        <flux:field>
                            <flux:label>{{ __('¿Autoriza el registro de datos de los menores de edad del hogar?') }}</flux:label>
                            <flux:select x-model="census_entry.consent_minors">
                                <flux:select.option value="si">{{ __('Sí') }}</flux:select.option>
                                <flux:select.option value="no">{{ __('No') }}</flux:select.option>
                                <flux:select.option value="no_aplica">{{ __('No aplica') }}</flux:select.option>
                            </flux:select>
                        </flux:field>
                        <flux:input x-model="census_entry.consent_given_by_name" :label="__('Nombre de quien autoriza')" />
                        <flux:input x-model="census_entry.consent_relationship" :label="__('Parentesco con el jefe de hogar')" />
                    </div>
                </template>

                <div class="flex justify-end">
                    <flux:button variant="primary" x-on:click="next()" x-bind:disabled="census_entry.consent_given !== true">{{ __('Continuar') }}</flux:button>
                </div>
            </div>
        </template>

        {{-- Paso 2: Ubicación, GPS y jefe de hogar (Módulos C y D) --}}
        <template x-if="step === 2">
            <div class="space-y-4">
                <flux:heading size="lg">{{ __('Ubicación y jefe de hogar') }}</flux:heading>

                <flux:field>
                    <flux:label>{{ __('Zona') }}</flux:label>
                    <flux:select x-model="family.zone_type">
                        <flux:select.option value="urbana">{{ __('Urbana') }}</flux:select.option>
                        <flux:select.option value="rural">{{ __('Rural') }}</flux:select.option>
                    </flux:select>
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Barrio / corregimiento / vereda') }}</flux:label>
                    <flux:select x-model="family.zone_id">
                        <flux:select.option value="">{{ __('Selecciona...') }}</flux:select.option>
                        @foreach ($this->zones as $zone)
                            <flux:select.option value="{{ $zone->id }}">{{ $zone->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </flux:field>

                <flux:input x-model="family.address" :label="__('Dirección exacta o punto de referencia')" />
                <flux:input x-model="family.phone" :label="__('Teléfono de contacto')" type="tel" />
                <flux:input x-model="family.route_code" :label="__('Manzana / predio (control de ruta, opcional)')" />

                <flux:field>
                    <flux:label>{{ __('Ubicación GPS') }}</flux:label>
                    <div class="flex items-center gap-3">
                        <flux:button x-on:click="captureGps()" x-bind:disabled="gpsStatus === 'locating'">{{ __('Capturar GPS') }}</flux:button>
                        <span class="text-sm text-muted" x-show="gpsStatus === 'ok'" x-text="`{{ __('Ubicación capturada') }} (±${family.gps_accuracy_meters ?? '?'} m)`"></span>
                        <span class="text-sm text-danger" x-show="gpsStatus === 'error'">{{ __('Sin señal GPS. Marca el punto manualmente en el mapa.') }}</span>
                    </div>
                    <template x-if="gpsStatus === 'error'">
                        <div wire:ignore x-data="censusFallbackMap()">
                            <div x-ref="mapContainer" class="h-64 mt-2 rounded-lg border-2 border-line"></div>
                            <p class="text-xs text-muted mt-1">{{ __('Toca el mapa para marcar la ubicación de la vivienda.') }}</p>
                        </div>
                    </template>
                </flux:field>

                <flux:separator />

                <flux:input x-model="family.head_full_name" :label="__('Nombres y apellidos del jefe de hogar')" />

                <flux:field>
                    <flux:label>{{ __('Tipo de documento') }}</flux:label>
                    <flux:select x-model="family.head_document_type">
                        <flux:select.option value="CC">{{ __('Cédula de ciudadanía') }}</flux:select.option>
                        <flux:select.option value="TI">{{ __('Tarjeta de identidad') }}</flux:select.option>
                        <flux:select.option value="CE">{{ __('Cédula de extranjería') }}</flux:select.option>
                        <flux:select.option value="PPT">{{ __('Permiso por Protección Temporal') }}</flux:select.option>
                        <flux:select.option value="pasaporte">{{ __('Pasaporte') }}</flux:select.option>
                        <flux:select.option value="RC">{{ __('Registro civil') }}</flux:select.option>
                        <flux:select.option value="NUIP">{{ __('NUIP') }}</flux:select.option>
                        <flux:select.option value="sin_documento">{{ __('Sin documento') }}</flux:select.option>
                    </flux:select>
                </flux:field>

                <flux:input x-model="family.head_document_number" :label="__('Número de documento')" />

                <flux:field>
                    <flux:label>{{ __('Sexo') }}</flux:label>
                    <flux:select x-model="family.head_sex">
                        <flux:select.option value="hombre">{{ __('Hombre') }}</flux:select.option>
                        <flux:select.option value="mujer">{{ __('Mujer') }}</flux:select.option>
                        <flux:select.option value="intersexual">{{ __('Intersexual') }}</flux:select.option>
                    </flux:select>
                </flux:field>

                <flux:input x-model="family.head_birthdate" :label="__('Fecha de nacimiento')" type="date" />

                <div class="flex justify-between">
                    <flux:button x-on:click="back()">{{ __('Atrás') }}</flux:button>
                    <flux:button variant="primary" x-on:click="next()">{{ __('Continuar') }}</flux:button>
                </div>
            </div>
        </template>

        {{-- Paso 3: Vivienda y servicios (Módulos F y G) --}}
        <template x-if="step === 3">
            <div class="space-y-4">
                <flux:heading size="lg">{{ __('Vivienda y servicios') }}</flux:heading>

                <flux:field>
                    <flux:label>{{ __('Nivel de afectación de la vivienda') }}</flux:label>
                    <flux:select x-model="family.housing_damage_level">
                        <flux:select.option value="destruida">{{ __('Destruida') }}</flux:select.option>
                        <flux:select.option value="averiada_no_habitable">{{ __('Averiada, no habitable') }}</flux:select.option>
                        <flux:select.option value="averiada_habitable">{{ __('Averiada, habitable') }}</flux:select.option>
                        <flux:select.option value="sin_dano">{{ __('Sin daño') }}</flux:select.option>
                    </flux:select>
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Marca de inspección técnica (semáforo)') }}</flux:label>
                    <flux:select x-model="family.housing_inspection_mark">
                        <flux:select.option value="verde">{{ __('Verde') }}</flux:select.option>
                        <flux:select.option value="amarillo">{{ __('Amarillo') }}</flux:select.option>
                        <flux:select.option value="naranja">{{ __('Naranja') }}</flux:select.option>
                        <flux:select.option value="rojo">{{ __('Rojo') }}</flux:select.option>
                        <flux:select.option value="sin_marca">{{ __('Sin marca') }}</flux:select.option>
                    </flux:select>
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Forma de tenencia') }}</flux:label>
                    <flux:select x-model="family.tenure_type">
                        <flux:select.option value="propia">{{ __('Propia') }}</flux:select.option>
                        <flux:select.option value="arrendada">{{ __('Arrendada') }}</flux:select.option>
                        <flux:select.option value="poseedor_ocupante">{{ __('Poseedor / ocupante') }}</flux:select.option>
                        <flux:select.option value="familiar_prestada">{{ __('Familiar / prestada') }}</flux:select.option>
                        <flux:select.option value="otra">{{ __('Otra') }}</flux:select.option>
                    </flux:select>
                </flux:field>

                <flux:input x-show="rentRequired" x-cloak x-model="family.monthly_rent" :label="__('Valor mensual de arriendo (COP)')" type="number" min="0" />

                <flux:input x-model="family.rooms_count" :label="__('Número de habitaciones (opcional)')" type="number" min="1" max="15" />
                <p class="text-xs text-muted">{{ __('El total de personas del hogar se pregunta en el siguiente paso (Composición del hogar).') }}</p>

                <flux:separator />

                <flux:field>
                    <flux:label>{{ __('¿Tiene acceso actual a agua para beber?') }}</flux:label>
                    <flux:select x-model="family.water_access">
                        <flux:select.option value="si">{{ __('Sí') }}</flux:select.option>
                        <flux:select.option value="no">{{ __('No') }}</flux:select.option>
                    </flux:select>
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Fuente principal de agua ahora') }}</flux:label>
                    <flux:select x-model="family.water_source">
                        <flux:select.option value="acueducto">{{ __('Acueducto') }}</flux:select.option>
                        <flux:select.option value="carrotanque">{{ __('Carrotanque') }}</flux:select.option>
                        <flux:select.option value="pozo">{{ __('Pozo') }}</flux:select.option>
                        <flux:select.option value="rio_quebrada">{{ __('Río / quebrada') }}</flux:select.option>
                        <flux:select.option value="agua_embotellada">{{ __('Agua embotellada') }}</flux:select.option>
                        <flux:select.option value="ninguna">{{ __('Ninguna') }}</flux:select.option>
                    </flux:select>
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('¿Servicio de energía eléctrica?') }}</flux:label>
                    <flux:select x-model="family.electricity_access">
                        <flux:select.option value="si">{{ __('Sí') }}</flux:select.option>
                        <flux:select.option value="no">{{ __('No') }}</flux:select.option>
                        <flux:select.option value="intermitente">{{ __('Intermitente') }}</flux:select.option>
                    </flux:select>
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('¿Dispone de sanitario o letrina utilizable?') }}</flux:label>
                    <flux:select x-model="family.sanitation_access">
                        <flux:select.option value="si">{{ __('Sí') }}</flux:select.option>
                        <flux:select.option value="no">{{ __('No') }}</flux:select.option>
                    </flux:select>
                </flux:field>

                <div class="flex justify-between">
                    <flux:button x-on:click="back()">{{ __('Atrás') }}</flux:button>
                    <flux:button variant="primary" x-on:click="next()">{{ __('Continuar') }}</flux:button>
                </div>
            </div>
        </template>

        {{-- Paso 4: Conteos del hogar e integrantes (Módulo E) --}}
        <template x-if="step === 4">
            <div class="space-y-4">
                <flux:heading size="lg">{{ __('Composición del hogar') }}</flux:heading>
                <flux:input x-model="census_entry.total_people" :label="__('Total de personas que habitan el hogar')" type="number" min="1" max="30" />
                <flux:input x-model="census_entry.under_5_count" :label="__('Menores de 5 años')" type="number" min="0" max="15" />
                <flux:input x-model="census_entry.over_60_count" :label="__('Mayores de 60 años')" type="number" min="0" max="15" />
                <flux:input x-model="census_entry.pregnant_lactating_count" :label="__('Mujeres gestantes o lactantes')" type="number" min="0" max="10" />
                <flux:input x-model="census_entry.disability_count" :label="__('Personas con discapacidad o dificultad funcional significativa')" type="number" min="0" max="15" />
                <flux:input x-model="census_entry.chronic_illness_count" :label="__('Personas con enfermedad crónica')" type="number" min="0" max="15" />

                <flux:separator />

                <div class="flex items-center justify-between">
                    <flux:heading size="lg">{{ __('Integrantes del hogar') }}</flux:heading>
                    <flux:button size="sm" x-on:click="addMember()">{{ __('Agregar integrante') }}</flux:button>
                </div>
                <p class="text-sm text-muted">{{ __('Opcional en el censo de triaje: agrega un renglón por cada persona si el tiempo lo permite.') }}</p>

                <template x-for="(member, index) in members" :key="index">
                    <div class="card-brutal p-4 space-y-3">
                        <div class="flex justify-between items-center">
                            <span class="font-display font-bold text-sm" x-text="`{{ __('Integrante') }} ${index + 1}`"></span>
                            <flux:button size="sm" variant="danger" x-on:click="removeMember(index)">{{ __('Quitar') }}</flux:button>
                        </div>
                        <flux:input x-model="member.full_name" :label="__('Nombres y apellidos')" />
                        <flux:input x-model="member.document_number" :label="__('Número de documento (opcional)')" />
                        <flux:input x-model="member.relationship_to_head" :label="__('Parentesco con el jefe de hogar')" />
                        <flux:field>
                            <flux:label>{{ __('Sexo') }}</flux:label>
                            <flux:select x-model="member.sex">
                                <flux:select.option value="hombre">{{ __('Hombre') }}</flux:select.option>
                                <flux:select.option value="mujer">{{ __('Mujer') }}</flux:select.option>
                                <flux:select.option value="intersexual">{{ __('Intersexual') }}</flux:select.option>
                            </flux:select>
                        </flux:field>
                        <flux:input x-model="member.birthdate" :label="__('Fecha de nacimiento')" type="date" />
                    </div>
                </template>

                <div class="flex justify-between">
                    <flux:button x-on:click="back()">{{ __('Atrás') }}</flux:button>
                    <flux:button variant="primary" x-on:click="next()">{{ __('Continuar') }}</flux:button>
                </div>
            </div>
        </template>

        {{-- Paso 5: Alimentación, salud, alojamiento, entorno y necesidades (Módulos H, I, J, K, L) --}}
        <template x-if="step === 5">
            <div class="space-y-4">
                <flux:heading size="lg">{{ __('Alimentación, salud y necesidades') }}</flux:heading>

                <flux:input x-model="census_entry.meals_yesterday" :label="__('¿Cuántas comidas consumió el hogar ayer?')" type="number" min="0" max="5" />

                <template x-if="showRcsi">
                    <div class="space-y-3">
                        <p class="text-sm text-muted">{{ __('En los últimos 7 días, ¿cuántos días el hogar tuvo que...') }}</p>
                        <flux:input x-model="census_entry.rcsi_less_preferred" :label="__('Comer alimentos menos preferidos o más baratos (0-7 días)')" type="number" min="0" max="7" />
                        <flux:input x-model="census_entry.rcsi_borrow_food" :label="__('Pedir prestado alimento o depender de ayuda (0-7 días)')" type="number" min="0" max="7" />
                        <flux:input x-model="census_entry.rcsi_reduce_portion" :label="__('Reducir el tamaño de las porciones (0-7 días)')" type="number" min="0" max="7" />
                        <flux:input x-model="census_entry.rcsi_reduce_adult_consumption" :label="__('Reducir el consumo de los adultos para que coman los niños (0-7 días)')" type="number" min="0" max="7" />
                        <flux:input x-model="census_entry.rcsi_reduce_meals" :label="__('Reducir el número de comidas al día (0-7 días)')" type="number" min="0" max="7" />
                    </div>
                </template>

                <flux:separator />

                <label class="flex items-center gap-2 text-sm text-ink">
                    <input type="checkbox" x-model="census_entry.injured" class="size-4 rounded border-line accent-accent">
                    {{ __('¿Alguna persona del hogar resultó herida en el sismo?') }}
                </label>
                <label class="flex items-center gap-2 text-sm text-ink">
                    <input type="checkbox" x-model="census_entry.needs_urgent_medical_attention" class="size-4 rounded border-line accent-accent">
                    {{ __('¿Alguien requiere atención médica urgente ahora?') }}
                </label>
                <label class="flex items-center gap-2 text-sm text-ink">
                    <input type="checkbox" x-model="census_entry.lost_permanent_medication" class="size-4 rounded border-line accent-accent">
                    {{ __('¿Alguien depende de un medicamento permanente que perdió?') }}
                </label>

                <flux:separator />

                <flux:field>
                    <flux:label>{{ __('¿Dónde está durmiendo el hogar ahora?') }}</flux:label>
                    <flux:select x-model="census_entry.sleeping_location">
                        <flux:select.option value="su_vivienda">{{ __('Su vivienda') }}</flux:select.option>
                        <flux:select.option value="casa_familiares_amigos">{{ __('Casa de familiares/amigos') }}</flux:select.option>
                        <flux:select.option value="albergue_oficial">{{ __('Albergue oficial') }}</flux:select.option>
                        <flux:select.option value="carpa">{{ __('Carpa') }}</flux:select.option>
                        <flux:select.option value="a_la_intemperie">{{ __('A la intemperie') }}</flux:select.option>
                    </flux:select>
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('¿Necesita alojamiento temporal?') }}</flux:label>
                    <flux:select x-model="census_entry.needs_temporary_shelter">
                        <flux:select.option value="si_urgente">{{ __('Sí, urgente') }}</flux:select.option>
                        <flux:select.option value="si">{{ __('Sí') }}</flux:select.option>
                        <flux:select.option value="no">{{ __('No') }}</flux:select.option>
                    </flux:select>
                </flux:field>

                <flux:separator />

                <flux:field>
                    <flux:label>{{ __('¿Riesgos en el entorno inmediato? (selección múltiple)') }}</flux:label>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                        <label class="flex items-center gap-2 text-sm text-ink">
                            <input type="checkbox" value="deslizamiento" x-model="census_entry.environment_risks" class="size-4 rounded border-line accent-accent">
                            {{ __('Deslizamiento') }}
                        </label>
                        <label class="flex items-center gap-2 text-sm text-ink">
                            <input type="checkbox" value="edificacion_en_riesgo" x-model="census_entry.environment_risks" class="size-4 rounded border-line accent-accent">
                            {{ __('Edificación en riesgo de colapso') }}
                        </label>
                        <label class="flex items-center gap-2 text-sm text-ink">
                            <input type="checkbox" value="vias_obstruidas" x-model="census_entry.environment_risks" class="size-4 rounded border-line accent-accent">
                            {{ __('Vías obstruidas') }}
                        </label>
                        <label class="flex items-center gap-2 text-sm text-ink">
                            <input type="checkbox" value="redes_electricas_caidas" x-model="census_entry.environment_risks" class="size-4 rounded border-line accent-accent">
                            {{ __('Redes eléctricas caídas') }}
                        </label>
                        <label class="flex items-center gap-2 text-sm text-ink">
                            <input type="checkbox" value="grietas_en_el_terreno" x-model="census_entry.environment_risks" class="size-4 rounded border-line accent-accent">
                            {{ __('Grietas en el terreno') }}
                        </label>
                        <label class="flex items-center gap-2 text-sm text-ink">
                            <input type="checkbox" value="ninguno" x-model="census_entry.environment_risks" class="size-4 rounded border-line accent-accent">
                            {{ __('Ninguno') }}
                        </label>
                    </div>
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('¿La vía de acceso al hogar está transitable?') }}</flux:label>
                    <flux:select x-model="census_entry.access_passable">
                        <flux:select.option value="si">{{ __('Sí') }}</flux:select.option>
                        <flux:select.option value="parcial">{{ __('Parcial') }}</flux:select.option>
                        <flux:select.option value="no">{{ __('No') }}</flux:select.option>
                    </flux:select>
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Las tres necesidades más urgentes del hogar (máx. 3)') }}</flux:label>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                        <label class="flex items-center gap-2 text-sm text-ink">
                            <input type="checkbox" value="agua" x-model="census_entry.priority_needs" class="size-4 rounded border-line accent-accent" x-bind:disabled="census_entry.priority_needs.length >= 3 && !census_entry.priority_needs.includes('agua')">
                            {{ __('Agua') }}
                        </label>
                        <label class="flex items-center gap-2 text-sm text-ink">
                            <input type="checkbox" value="alimentos" x-model="census_entry.priority_needs" class="size-4 rounded border-line accent-accent" x-bind:disabled="census_entry.priority_needs.length >= 3 && !census_entry.priority_needs.includes('alimentos')">
                            {{ __('Alimentos') }}
                        </label>
                        <label class="flex items-center gap-2 text-sm text-ink">
                            <input type="checkbox" value="techo_carpa" x-model="census_entry.priority_needs" class="size-4 rounded border-line accent-accent" x-bind:disabled="census_entry.priority_needs.length >= 3 && !census_entry.priority_needs.includes('techo_carpa')">
                            {{ __('Techo / carpa') }}
                        </label>
                        <label class="flex items-center gap-2 text-sm text-ink">
                            <input type="checkbox" value="abrigo_cobijas" x-model="census_entry.priority_needs" class="size-4 rounded border-line accent-accent" x-bind:disabled="census_entry.priority_needs.length >= 3 && !census_entry.priority_needs.includes('abrigo_cobijas')">
                            {{ __('Abrigo / cobijas') }}
                        </label>
                        <label class="flex items-center gap-2 text-sm text-ink">
                            <input type="checkbox" value="kit_higiene" x-model="census_entry.priority_needs" class="size-4 rounded border-line accent-accent" x-bind:disabled="census_entry.priority_needs.length >= 3 && !census_entry.priority_needs.includes('kit_higiene')">
                            {{ __('Kit de higiene') }}
                        </label>
                        <label class="flex items-center gap-2 text-sm text-ink">
                            <input type="checkbox" value="salud_medicamentos" x-model="census_entry.priority_needs" class="size-4 rounded border-line accent-accent" x-bind:disabled="census_entry.priority_needs.length >= 3 && !census_entry.priority_needs.includes('salud_medicamentos')">
                            {{ __('Salud / medicamentos') }}
                        </label>
                        <label class="flex items-center gap-2 text-sm text-ink">
                            <input type="checkbox" value="dinero" x-model="census_entry.priority_needs" class="size-4 rounded border-line accent-accent" x-bind:disabled="census_entry.priority_needs.length >= 3 && !census_entry.priority_needs.includes('dinero')">
                            {{ __('Dinero') }}
                        </label>
                        <label class="flex items-center gap-2 text-sm text-ink">
                            <input type="checkbox" value="informacion" x-model="census_entry.priority_needs" class="size-4 rounded border-line accent-accent" x-bind:disabled="census_entry.priority_needs.length >= 3 && !census_entry.priority_needs.includes('informacion')">
                            {{ __('Información') }}
                        </label>
                    </div>
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('¿Está ya inscrito en el RUD por este evento?') }}</flux:label>
                    <flux:select x-model="census_entry.registered_in_rud">
                        <flux:select.option value="si">{{ __('Sí') }}</flux:select.option>
                        <flux:select.option value="no">{{ __('No') }}</flux:select.option>
                        <flux:select.option value="no_sabe">{{ __('No sabe') }}</flux:select.option>
                    </flux:select>
                </flux:field>

                <div class="flex justify-between">
                    <flux:button x-on:click="back()">{{ __('Atrás') }}</flux:button>
                    <flux:button variant="primary" x-on:click="next()">{{ __('Continuar') }}</flux:button>
                </div>
            </div>
        </template>

        {{-- Paso 6: Cierre y envío (Módulo M) --}}
        <template x-if="step === 6">
            <div class="space-y-4">
                <flux:heading size="lg">{{ __('Cierre de la visita') }}</flux:heading>

                <flux:field>
                    <flux:label>{{ __('Verificación visual: ¿el daño observado concuerda con lo declarado?') }}</flux:label>
                    <flux:select x-model="census_entry.damage_verified">
                        <flux:select.option value="si">{{ __('Sí') }}</flux:select.option>
                        <flux:select.option value="parcial">{{ __('Parcial') }}</flux:select.option>
                        <flux:select.option value="no">{{ __('No') }}</flux:select.option>
                    </flux:select>
                </flux:field>

                <label class="flex items-center gap-2 text-sm text-ink">
                    <input type="checkbox" x-model="census_entry.needs_structural_assessment" class="size-4 rounded border-line accent-accent">
                    {{ __('¿Requiere visita de verificación técnica estructural?') }}
                </label>

                <flux:callout variant="secondary">
                    {{ __('Fecha y hora de la visita') }}: <span x-text="census_entry.surveyed_at"></span>
                </flux:callout>

                <div class="flex justify-between">
                    <flux:button x-on:click="back()" x-bind:disabled="submitting">{{ __('Atrás') }}</flux:button>
                    <flux:button variant="primary" x-on:click="submit()" x-bind:disabled="submitting">
                        <span x-show="!submitting">{{ __('Guardar captura') }}</span>
                        <span x-show="submitting" x-cloak>{{ __('Guardando...') }}</span>
                    </flux:button>
                </div>
            </div>
        </template>
    </div>
</section>
