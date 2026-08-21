<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        $commonVariables = [
            ['key' => 'resident_full_name', 'label' => 'Resident Full Name'],
            ['key' => 'resident_civil_status', 'label' => 'Resident Civil Status'],
            ['key' => 'household_address', 'label' => 'Household Complete Address'],
            ['key' => 'purpose', 'label' => 'Request Purpose'],
            ['key' => 'purok_name', 'label' => 'Purok Name'],
            ['key' => 'project_name', 'label' => 'System / Barangay Name'],
            ['key' => 'today', 'label' => "Today's Date"],
        ];

        $header = function (string $title): string {
            return '<p class="ql-align-center"><strong>Republic of the Philippines</strong></p>'
                .'<p class="ql-align-center">{{purok_name}}, {{project_name}}</p>'
                .'<p class="ql-align-center"><strong>OFFICE OF THE PUNONG BARANGAY</strong></p>'
                .'<p class="ql-align-center"><br></p>'
                .'<p class="ql-align-center"><strong>'.$title.'</strong></p>'
                .'<p class="ql-align-center"><br></p>'
                .'<p>TO WHOM IT MAY CONCERN:</p>'
                .'<p><br></p>';
        };

        $footer = '<p><br></p>'
            .'<p>This certification is issued upon the request of the above-named person for the purpose of <strong>{{purpose}}</strong>, and for whatever legal intent it may serve.</p>'
            .'<p><br></p>'
            .'<p>Issued this {{today}} at {{purok_name}}, {{project_name}}.</p>'
            .'<p><br></p><p><br></p><p><br></p>'
            .'<p class="ql-align-center">_________________________</p>'
            .'<p class="ql-align-center">Punong Barangay</p>';

        // 1. Create the "Certificate of Single Parent" service type if it doesn't already exist.
        $singleParentTypeId = DB::table('service_types')->where('code', 'CERT-SP')->value('id');
        if (! $singleParentTypeId) {
            $singleParentTypeId = DB::table('service_types')->insertGetId([
                'code' => 'CERT-SP',
                'name' => 'Certificate of Single Parent',
                'description' => 'Certification that the resident is recognized as a solo parent.',
                'fee' => 0,
                'requirements' => null,
                'processing_days' => null,
                'approval_required' => true,
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // 2. Create the two new clean templates.
        $newTemplates = [
            [
                'code' => 'CERT-IND-TPL',
                'name' => 'Certificate of Indigency',
                'title' => 'CERTIFICATE OF INDIGENCY',
                'body' => 'This is to certify that <strong>{{resident_full_name}}</strong>, of legal age, {{resident_civil_status}}, and a resident of {{household_address}}, is known to be a person of good moral character and a member of an indigent family in this barangay, based on the record and representation made before this office.',
                'service_type_code' => 'CERT-IND',
            ],
            [
                'code' => 'CERT-SP-TPL',
                'name' => 'Certificate of Single Parent',
                'title' => 'CERTIFICATE OF SINGLE PARENT',
                'body' => 'This is to certify that <strong>{{resident_full_name}}</strong>, of legal age, {{resident_civil_status}}, and a resident of {{household_address}}, is known to this office to be a solo parent, having sole responsibility for the support and rearing of their child/children, based on the record and representation made before this office.',
                'service_type_code' => 'CERT-SP',
            ],
        ];

        $newTemplateIds = [];

        foreach ($newTemplates as $template) {
            $existingId = DB::table('document_templates')->where('code', $template['code'])->value('id');
            if ($existingId) {
                $newTemplateIds[$template['service_type_code']] = $existingId;
                continue;
            }

            $contentHtml = $header($template['title']).'<p>'.$template['body'].'</p>'.$footer;

            $templateId = DB::table('document_templates')->insertGetId([
                'code' => $template['code'],
                'name' => $template['name'],
                'description' => 'Default certificate template for '.$template['name'].'.',
                'content_html' => $contentHtml,
                'variables' => json_encode($commonVariables),
                'logo_placements' => json_encode([]),
                'document_type' => 'certificate',
                'kp_stage' => null,
                'paper_size' => 'a4',
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $newTemplateIds[$template['service_type_code']] = $templateId;
        }

        // 3. Point both service types at their new templates.
        DB::table('service_types')->where('code', 'CERT-IND')->update([
            'document_template_id' => $newTemplateIds['CERT-IND'],
            'updated_at' => $now,
        ]);
        DB::table('service_types')->where('id', $singleParentTypeId)->update([
            'document_template_id' => $newTemplateIds['CERT-SP'],
            'updated_at' => $now,
        ]);

        // 4. Delete the old templates now that nothing references them.
        DB::table('document_templates')->whereIn('code', ['coi', 'DT-2026-71438'])->delete();
    }

    public function down(): void
    {
        DB::table('service_types')->where('code', 'CERT-IND')->update(['document_template_id' => null]);
        DB::table('service_types')->where('code', 'CERT-SP')->delete();

        $templateIds = DB::table('document_templates')->whereIn('code', ['CERT-IND-TPL', 'CERT-SP-TPL'])->pluck('id');
        DB::table('document_templates')->whereIn('id', $templateIds)->delete();
    }
};
