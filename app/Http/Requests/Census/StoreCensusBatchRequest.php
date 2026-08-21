<?php

namespace App\Http\Requests\Census;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Valida el lote de capturas del censo Fase 1 (triaje) enviado por el wizard offline-first.
 * El cliente siempre envía un arreglo `entries`, incluso cuando hay una sola captura
 * (envío en línea): esto unifica el mismo endpoint para el envío directo y para el
 * vaciado de la cola de sincronización offline.
 */
class StoreCensusBatchRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'entries' => ['required', 'array', 'min:1'],
            'entries.*.client_uuid' => ['required', 'uuid'],

            'entries.*.family.zone_id' => ['nullable', 'integer', 'exists:geographic_zones,id'],
            'entries.*.family.zone_type' => ['required', 'in:urbana,rural'],
            'entries.*.family.neighborhood' => ['nullable', 'string', 'max:150'],
            'entries.*.family.address' => ['required', 'string', 'max:255'],
            'entries.*.family.phone' => ['required', 'string', 'max:20'],
            'entries.*.family.route_code' => ['nullable', 'string', 'max:50'],
            'entries.*.family.latitude' => ['required', 'numeric', 'between:-90,90'],
            'entries.*.family.longitude' => ['required', 'numeric', 'between:-180,180'],
            'entries.*.family.gps_accuracy_meters' => ['nullable', 'integer', 'min:0'],
            'entries.*.family.gps_captured_at' => ['nullable', 'date'],
            'entries.*.family.head_full_name' => ['required', 'string', 'max:150'],
            'entries.*.family.head_document_type' => ['required', 'in:CC,TI,CE,PPT,pasaporte,RC,NUIP,sin_documento'],
            'entries.*.family.head_document_number' => ['nullable', 'string', 'max:30'],
            'entries.*.family.head_sex' => ['required', 'in:hombre,mujer,intersexual'],
            'entries.*.family.head_birthdate' => ['nullable', 'date', 'before:today'],
            'entries.*.family.head_gender_identity' => ['nullable', 'string', 'max:30'],
            'entries.*.family.housing_damage_level' => ['required', 'in:destruida,averiada_no_habitable,averiada_habitable,sin_dano'],
            'entries.*.family.housing_inspection_mark' => ['nullable', 'in:verde,amarillo,naranja,rojo,sin_marca'],
            'entries.*.family.tenure_type' => ['required', 'in:propia,arrendada,poseedor_ocupante,familiar_prestada,otra'],
            'entries.*.family.monthly_rent' => ['nullable', 'integer', 'min:0', 'required_if:entries.*.family.tenure_type,arrendada'],
            'entries.*.family.water_access' => ['required', 'in:si,no'],
            'entries.*.family.water_source' => ['required', 'in:acueducto,carrotanque,pozo,rio_quebrada,agua_embotellada,ninguna'],
            'entries.*.family.electricity_access' => ['required', 'in:si,no,intermitente'],
            'entries.*.family.sanitation_access' => ['required', 'in:si,no'],
            'entries.*.family.rooms_count' => ['nullable', 'integer', 'min:1', 'max:15'],

            'entries.*.census_entry.surveyed_at' => ['required', 'date'],
            'entries.*.census_entry.surveyor_entity' => ['required', 'in:alcaldia_cmgrd,cruz_roja,defensa_civil,bomberos,donaciones_rolda,otra'],
            'entries.*.census_entry.consent_given' => ['required', 'accepted'],
            'entries.*.census_entry.consent_minors' => ['nullable', 'in:si,no,no_aplica'],
            'entries.*.census_entry.consent_given_by_name' => ['required', 'string', 'max:150'],
            'entries.*.census_entry.consent_relationship' => ['required', 'string', 'max:60'],

            'entries.*.census_entry.total_people' => ['required', 'integer', 'min:1', 'max:30'],
            'entries.*.census_entry.under_5_count' => ['required', 'integer', 'min:0', 'max:15'],
            'entries.*.census_entry.over_60_count' => ['required', 'integer', 'min:0', 'max:15'],
            'entries.*.census_entry.pregnant_lactating_count' => ['required', 'integer', 'min:0', 'max:10'],
            'entries.*.census_entry.disability_count' => ['required', 'integer', 'min:0', 'max:15'],
            'entries.*.census_entry.chronic_illness_count' => ['required', 'integer', 'min:0', 'max:15'],

            'entries.*.census_entry.meals_yesterday' => ['required', 'integer', 'min:0', 'max:5'],
            'entries.*.census_entry.rcsi_less_preferred' => ['nullable', 'integer', 'min:0', 'max:7'],
            'entries.*.census_entry.rcsi_borrow_food' => ['nullable', 'integer', 'min:0', 'max:7'],
            'entries.*.census_entry.rcsi_reduce_portion' => ['nullable', 'integer', 'min:0', 'max:7'],
            'entries.*.census_entry.rcsi_reduce_adult_consumption' => ['nullable', 'integer', 'min:0', 'max:7'],
            'entries.*.census_entry.rcsi_reduce_meals' => ['nullable', 'integer', 'min:0', 'max:7'],

            'entries.*.census_entry.injured' => ['required', 'boolean'],
            'entries.*.census_entry.needs_urgent_medical_attention' => ['required', 'boolean'],
            'entries.*.census_entry.lost_permanent_medication' => ['required', 'boolean'],

            'entries.*.census_entry.sleeping_location' => ['required', 'in:su_vivienda,casa_familiares_amigos,albergue_oficial,carpa,a_la_intemperie'],
            'entries.*.census_entry.needs_temporary_shelter' => ['required', 'in:si_urgente,si,no'],

            'entries.*.census_entry.environment_risks' => ['nullable', 'array'],
            'entries.*.census_entry.environment_risks.*' => ['string', 'in:deslizamiento,edificacion_en_riesgo,vias_obstruidas,redes_electricas_caidas,grietas_en_el_terreno,ninguno'],
            'entries.*.census_entry.access_passable' => ['required', 'in:si,parcial,no'],

            'entries.*.census_entry.priority_needs' => ['required', 'array', 'min:1', 'max:3'],
            'entries.*.census_entry.priority_needs.*' => ['string', 'in:agua,alimentos,techo_carpa,abrigo_cobijas,kit_higiene,salud_medicamentos,dinero,informacion'],
            'entries.*.census_entry.registered_in_rud' => ['required', 'in:si,no,no_sabe'],

            'entries.*.census_entry.damage_verified' => ['required', 'in:si,parcial,no'],
            'entries.*.census_entry.needs_structural_assessment' => ['required', 'boolean'],

            'entries.*.members' => ['nullable', 'array'],
            'entries.*.members.*.full_name' => ['required', 'string', 'max:150'],
            'entries.*.members.*.document_type' => ['nullable', 'in:CC,TI,CE,PPT,RC,NUIP,sin_documento'],
            'entries.*.members.*.document_number' => ['nullable', 'string', 'max:30'],
            'entries.*.members.*.relationship_to_head' => ['nullable', 'string', 'max:60'],
            'entries.*.members.*.sex' => ['nullable', 'in:hombre,mujer,intersexual'],
            'entries.*.members.*.birthdate' => ['nullable', 'date', 'before:today'],
            'entries.*.members.*.is_household_head' => ['nullable', 'boolean'],
        ];
    }
}
