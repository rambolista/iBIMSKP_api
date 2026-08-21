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

        $templates = [
            [
                'code' => 'BAR-CLEAR-TPL',
                'name' => 'Barangay Clearance',
                'service_type_code' => 'BAR-CLEAR',
                'title' => 'BARANGAY CLEARANCE',
                'body' => 'This is to certify that <strong>{{resident_full_name}}</strong>, of legal age, {{resident_civil_status}}, and a resident of {{household_address}}, is known to be a person of good moral character and standing in this barangay, with no derogatory record on file, and has no pending case or complaint before the Barangay as of the date of this issuance.',
            ],
            [
                'code' => 'CERT-RES-TPL',
                'name' => 'Certificate of Residency',
                'service_type_code' => 'CERT-RES',
                'title' => 'CERTIFICATE OF RESIDENCY',
                'body' => 'This is to certify that <strong>{{resident_full_name}}</strong>, of legal age, {{resident_civil_status}}, is a bona fide resident of {{household_address}}, and has been residing within this barangay.',
            ],
            [
                'code' => 'CERT-GMC-TPL',
                'name' => 'Certificate of Good Moral Character',
                'service_type_code' => 'CERT-GMC',
                'title' => 'CERTIFICATE OF GOOD MORAL CHARACTER',
                'body' => 'This is to certify that <strong>{{resident_full_name}}</strong>, of legal age, {{resident_civil_status}}, and a resident of {{household_address}}, is personally known to this office to be a person of good moral character and reputable standing, having observed good conduct in this community.',
            ],
            [
                'code' => 'CERT-EMP-TPL',
                'name' => 'Certificate of Employment',
                'service_type_code' => 'CERT-EMP',
                'title' => 'CERTIFICATE OF EMPLOYMENT',
                'body' => 'This is to certify that <strong>{{resident_full_name}}</strong>, of legal age, {{resident_civil_status}}, and a resident of {{household_address}}, is presently engaged in gainful employment/occupation as <strong>{{resident_occupation}}</strong>, under <strong>{{resident_employer}}</strong>, based on the record and representation made before this office.',
                'extra_variables' => [
                    ['key' => 'resident_occupation', 'label' => 'Resident Occupation'],
                    ['key' => 'resident_employer', 'label' => 'Resident Employer'],
                ],
            ],
            [
                'code' => 'CERT-NI-TPL',
                'name' => 'Certificate of No Income',
                'service_type_code' => 'CERT-NI',
                'title' => 'CERTIFICATE OF NO INCOME',
                'body' => 'This is to certify that <strong>{{resident_full_name}}</strong>, of legal age, {{resident_civil_status}}, and a resident of {{household_address}}, has, based on the record and representation made before this office, no source of income at present.',
            ],
            [
                'code' => 'CERT-NE-TPL',
                'name' => 'Certificate of Non-Employment',
                'service_type_code' => 'CERT-NE',
                'title' => 'CERTIFICATE OF NON-EMPLOYMENT',
                'body' => 'This is to certify that <strong>{{resident_full_name}}</strong>, of legal age, {{resident_civil_status}}, and a resident of {{household_address}}, is not currently employed, based on the record and representation made before this office.',
            ],
            [
                'code' => 'CERT-LI-TPL',
                'name' => 'Certificate of Low Income',
                'service_type_code' => 'CERT-LI',
                'title' => 'CERTIFICATE OF LOW INCOME',
                'body' => 'This is to certify that <strong>{{resident_full_name}}</strong>, of legal age, {{resident_civil_status}}, and a resident of {{household_address}}, belongs to a household whose income, based on the record and representation made before this office, falls within the low-income bracket of this community.',
            ],
            [
                'code' => 'OTHER-CERT-TPL',
                'name' => 'General / Other Certification',
                'service_type_code' => 'OTHER-CERT',
                'title' => 'CERTIFICATION',
                'body' => 'This is to certify that <strong>{{resident_full_name}}</strong>, of legal age, {{resident_civil_status}}, and a resident of {{household_address}}, is a resident of this barangay in good standing.',
            ],
        ];

        foreach ($templates as $template) {
            $contentHtml = $header($template['title'])
                .'<p>'.$template['body'].'</p>'
                .$footer;

            $variables = array_merge($commonVariables, $template['extra_variables'] ?? []);

            $templateId = DB::table('document_templates')->where('code', $template['code'])->value('id');

            if ($templateId) {
                continue;
            }

            $templateId = DB::table('document_templates')->insertGetId([
                'code' => $template['code'],
                'name' => $template['name'],
                'description' => 'Default certificate template for '.$template['name'].'.',
                'content_html' => $contentHtml,
                'variables' => json_encode($variables),
                'logo_placements' => json_encode([]),
                'document_type' => 'certificate',
                'kp_stage' => null,
                'paper_size' => 'a4',
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('service_types')
                ->where('code', $template['service_type_code'])
                ->whereNull('document_template_id')
                ->update(['document_template_id' => $templateId, 'updated_at' => $now]);
        }
    }

    public function down(): void
    {
        $codes = ['BAR-CLEAR-TPL', 'CERT-RES-TPL', 'CERT-GMC-TPL', 'CERT-EMP-TPL', 'CERT-NI-TPL', 'CERT-NE-TPL', 'CERT-LI-TPL', 'OTHER-CERT-TPL'];
        $templateIds = DB::table('document_templates')->whereIn('code', $codes)->pluck('id');

        DB::table('service_types')->whereIn('document_template_id', $templateIds)->update(['document_template_id' => null]);
        DB::table('document_templates')->whereIn('id', $templateIds)->delete();
    }
};
