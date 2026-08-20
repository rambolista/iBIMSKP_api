<?php

namespace App\Support;

final class KpFormTemplateCatalog
{
    public static function definitions(): array
    {
        return [
            ['code' => 'KP-FORM-1', 'name' => 'KP Form 1 - Notice to Constitute the Lupon', 'variant' => 'notice_to_constitute_lupon'],
            ['code' => 'KP-FORM-2', 'name' => 'KP Form 2 - Appointment', 'variant' => 'appointment'],
            ['code' => 'KP-FORM-3', 'name' => 'KP Form 3 - Notice of Appointment', 'variant' => 'notice_of_appointment'],
            ['code' => 'KP-FORM-4', 'name' => 'KP Form 4 - List of Appointed Lupon Members', 'variant' => 'lupon_member_list'],
            ['code' => 'KP-FORM-5', 'name' => 'KP Form 5 - Oath of Office', 'variant' => 'oath_of_office'],
            ['code' => 'KP-FORM-6', 'name' => 'KP Form 6 - Withdrawal of Appointment', 'variant' => 'withdrawal_of_appointment'],
            ['code' => 'KP-FORM-7', 'name' => 'KP Form 7 - Complaint', 'variant' => 'complaint'],
            ['code' => 'KP-FORM-8', 'name' => 'KP Form 8 - Notice of Hearing (Mediation)', 'variant' => 'mediation_notice'],
            ['code' => 'KP-FORM-9', 'name' => 'KP Form 9 - Summons', 'variant' => 'summons'],
            ['code' => 'KP-FORM-10', 'name' => 'KP Form 10 - Notice for Constitution of Pangkat', 'variant' => 'notice_constitute_pangkat'],
            ['code' => 'KP-FORM-11', 'name' => 'KP Form 11 - Notice to Chosen Pangkat Member', 'variant' => 'notice_chosen_pangkat_member'],
            ['code' => 'KP-FORM-12', 'name' => 'KP Form 12 - Notice of Hearing (Conciliation)', 'variant' => 'conciliation_notice'],
            ['code' => 'KP-FORM-13', 'name' => 'KP Form 13 - Subpoena', 'variant' => 'subpoena'],
            ['code' => 'KP-FORM-14', 'name' => 'KP Form 14 - Agreement for Arbitration', 'variant' => 'agreement_for_arbitration'],
            ['code' => 'KP-FORM-15', 'name' => 'KP Form 15 - Arbitration Award', 'variant' => 'arbitration_award'],
            ['code' => 'KP-FORM-16', 'name' => 'KP Form 16 - Amicable Settlement', 'variant' => 'amicable_settlement'],
            ['code' => 'KP-FORM-17', 'name' => 'KP Form 17 - Repudiation', 'variant' => 'repudiation'],
            ['code' => 'KP-FORM-18', 'name' => 'KP Form 18 - Notice of Hearing to Complainant (Failure to Appear)', 'variant' => 'notice_complainant_failure'],
            ['code' => 'KP-FORM-19', 'name' => 'KP Form 19 - Notice of Hearing to Respondent (Failure to Appear)', 'variant' => 'notice_respondent_failure'],
            ['code' => 'KP-FORM-20', 'name' => 'KP Form 20 - Certificate to File Action', 'variant' => 'certificate_to_file_action'],
            ['code' => 'KP-FORM-20-A', 'name' => 'KP Form 20-A - Certificate to File Action from Lupon Secretary', 'variant' => 'certificate_file_action_lupon'],
            ['code' => 'KP-FORM-20-B', 'name' => 'KP Form 20-B - Certificate to File Action from Pangkat Secretary', 'variant' => 'certificate_file_action_pangkat'],
            ['code' => 'KP-FORM-21', 'name' => 'KP Form 21 - Certificate to Bar Action', 'variant' => 'certificate_to_bar_action'],
            ['code' => 'KP-FORM-22', 'name' => 'KP Form 22 - Certificate to Bar Counterclaim', 'variant' => 'certificate_to_bar_counterclaim'],
            ['code' => 'KP-FORM-23', 'name' => 'KP Form 23 - Motion for Execution', 'variant' => 'motion_for_execution'],
            ['code' => 'KP-FORM-24', 'name' => 'KP Form 24 - Notice of Hearing (Motion for Execution)', 'variant' => 'notice_motion_execution'],
            ['code' => 'KP-FORM-25', 'name' => 'KP Form 25 - Notice of Execution', 'variant' => 'notice_of_execution'],
        ];
    }

    public static function codes(): array
    {
        return array_column(self::definitions(), 'code');
    }

    public static function payload(array $definition, $timestamp): array
    {
        return [
            'name' => $definition['name'],
            'description' => self::description($definition['name']),
            'content_html' => self::contentHtml($definition['name'], $definition['variant']),
            'variables' => json_encode(self::variables($definition['variant']), JSON_UNESCAPED_SLASHES),
            'logo_placements' => json_encode([], JSON_UNESCAPED_SLASHES),
            'document_type' => 'kp_forms',
            'paper_size' => 'custom',
            'status' => 'active',
            'created_by' => null,
            'updated_by' => null,
            'archived_by' => null,
            'archived_at' => null,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ];
    }

    public static function description(string $name): string
    {
        return sprintf('Original editable KP form scaffold for %s with detailed sections, placeholders, and signatory blocks.', $name);
    }

    public static function variables(string $variant): array
    {
        $common = [
            ['key' => 'barangay_name', 'label' => 'Barangay Name'],
            ['key' => 'barangay_address', 'label' => 'Barangay Address'],
            ['key' => 'city_municipality', 'label' => 'City / Municipality'],
            ['key' => 'province', 'label' => 'Province'],
            ['key' => 'reference_number', 'label' => 'Reference Number'],
            ['key' => 'case_number', 'label' => 'Case Number'],
            ['key' => 'issuance_date', 'label' => 'Issuance Date'],
            ['key' => 'punong_barangay_name', 'label' => 'Punong Barangay Name'],
            ['key' => 'lupon_secretary_name', 'label' => 'Lupon Secretary Name'],
            ['key' => 'pangkat_chairperson_name', 'label' => 'Pangkat Chairperson Name'],
            ['key' => 'complainant_name', 'label' => 'Complainant Name'],
            ['key' => 'complainant_address', 'label' => 'Complainant Address'],
            ['key' => 'respondent_name', 'label' => 'Respondent Name'],
            ['key' => 'respondent_address', 'label' => 'Respondent Address'],
            ['key' => 'dispute_summary', 'label' => 'Dispute Summary'],
            ['key' => 'service_notes', 'label' => 'Service / Delivery Notes'],
            ['key' => 'prepared_by_name', 'label' => 'Prepared By Name'],
            ['key' => 'prepared_by_title', 'label' => 'Prepared By Title'],
            ['key' => 'approved_by_name', 'label' => 'Approved / Noted By Name'],
            ['key' => 'approved_by_title', 'label' => 'Approved / Noted By Title'],
        ];

        $variantSpecific = match ($variant) {
            'notice_to_constitute_lupon' => [
                ['key' => 'resolution_number', 'label' => 'Resolution Number'],
                ['key' => 'constitution_basis', 'label' => 'Basis for Constitution'],
                ['key' => 'term_coverage', 'label' => 'Term Coverage'],
                ['key' => 'member_selection_notes', 'label' => 'Member Selection Notes'],
            ],
            'appointment', 'notice_of_appointment', 'withdrawal_of_appointment' => [
                ['key' => 'appointee_name', 'label' => 'Appointee Name'],
                ['key' => 'appointee_address', 'label' => 'Appointee Address'],
                ['key' => 'appointee_position', 'label' => 'Appointee Position'],
                ['key' => 'term_start', 'label' => 'Term Start Date'],
                ['key' => 'term_end', 'label' => 'Term End Date'],
                ['key' => 'appointment_notes', 'label' => 'Appointment Notes'],
                ['key' => 'withdrawal_reason', 'label' => 'Withdrawal Reason'],
            ],
            'lupon_member_list' => [
                ['key' => 'lupon_member_list', 'label' => 'Lupon Member List'],
                ['key' => 'member_count', 'label' => 'Number of Members'],
                ['key' => 'roster_notes', 'label' => 'Roster Notes'],
            ],
            'oath_of_office' => [
                ['key' => 'officer_name', 'label' => 'Officer Name'],
                ['key' => 'officer_position', 'label' => 'Officer Position'],
                ['key' => 'oath_date', 'label' => 'Oath Date'],
                ['key' => 'administering_officer', 'label' => 'Administering Officer'],
                ['key' => 'oath_commitments', 'label' => 'Oath Commitments'],
            ],
            'complaint' => [
                ['key' => 'complaint_date', 'label' => 'Complaint Date'],
                ['key' => 'incident_date', 'label' => 'Incident / Event Date'],
                ['key' => 'incident_location', 'label' => 'Incident Location'],
                ['key' => 'complaint_narrative', 'label' => 'Complaint Narrative'],
                ['key' => 'supporting_facts', 'label' => 'Supporting Facts'],
                ['key' => 'relief_requested', 'label' => 'Relief Requested'],
                ['key' => 'witness_list', 'label' => 'Witness List'],
                ['key' => 'attachment_list', 'label' => 'Attachments / Evidence List'],
            ],
            'mediation_notice', 'conciliation_notice', 'summons', 'subpoena', 'notice_motion_execution' => [
                ['key' => 'recipient_name', 'label' => 'Recipient Name'],
                ['key' => 'recipient_address', 'label' => 'Recipient Address'],
                ['key' => 'hearing_date', 'label' => 'Hearing Date'],
                ['key' => 'hearing_time', 'label' => 'Hearing Time'],
                ['key' => 'hearing_location', 'label' => 'Hearing Location'],
                ['key' => 'hearing_purpose', 'label' => 'Purpose of Hearing'],
                ['key' => 'attendance_instructions', 'label' => 'Attendance Instructions'],
                ['key' => 'nonappearance_effect', 'label' => 'Effect of Non-Appearance'],
            ],
            'notice_constitute_pangkat', 'notice_chosen_pangkat_member' => [
                ['key' => 'selection_date', 'label' => 'Selection Date'],
                ['key' => 'selection_time', 'label' => 'Selection Time'],
                ['key' => 'selection_location', 'label' => 'Selection Location'],
                ['key' => 'selection_process_notes', 'label' => 'Selection Process Notes'],
                ['key' => 'chosen_member_name', 'label' => 'Chosen Member Name'],
                ['key' => 'chosen_member_address', 'label' => 'Chosen Member Address'],
            ],
            'agreement_for_arbitration' => [
                ['key' => 'agreement_date', 'label' => 'Agreement Date'],
                ['key' => 'arbitration_scope', 'label' => 'Arbitration Scope'],
                ['key' => 'arbitrator_name', 'label' => 'Arbitrator Name'],
                ['key' => 'agreed_issues', 'label' => 'Agreed Issues'],
                ['key' => 'party_commitments', 'label' => 'Party Commitments'],
            ],
            'arbitration_award' => [
                ['key' => 'award_date', 'label' => 'Award Date'],
                ['key' => 'award_terms', 'label' => 'Award Terms'],
                ['key' => 'findings_summary', 'label' => 'Findings Summary'],
                ['key' => 'compliance_period', 'label' => 'Compliance Period'],
            ],
            'amicable_settlement' => [
                ['key' => 'settlement_date', 'label' => 'Settlement Date'],
                ['key' => 'settlement_terms', 'label' => 'Settlement Terms'],
                ['key' => 'obligation_schedule', 'label' => 'Obligation Schedule'],
                ['key' => 'witness_names', 'label' => 'Witness Names'],
            ],
            'repudiation' => [
                ['key' => 'repudiating_party', 'label' => 'Repudiating Party'],
                ['key' => 'repudiation_date', 'label' => 'Repudiation Date'],
                ['key' => 'repudiation_reason', 'label' => 'Repudiation Reason'],
                ['key' => 'supporting_statement', 'label' => 'Supporting Statement'],
            ],
            'notice_complainant_failure', 'notice_respondent_failure' => [
                ['key' => 'recipient_name', 'label' => 'Recipient Name'],
                ['key' => 'recipient_address', 'label' => 'Recipient Address'],
                ['key' => 'missed_hearing_date', 'label' => 'Missed Hearing Date'],
                ['key' => 'next_hearing_date', 'label' => 'Next Hearing Date'],
                ['key' => 'next_hearing_time', 'label' => 'Next Hearing Time'],
                ['key' => 'next_hearing_location', 'label' => 'Next Hearing Location'],
                ['key' => 'failure_consequence', 'label' => 'Consequence of Failure to Appear'],
            ],
            'certificate_to_file_action', 'certificate_file_action_lupon', 'certificate_file_action_pangkat', 'certificate_to_bar_action', 'certificate_to_bar_counterclaim' => [
                ['key' => 'certification_date', 'label' => 'Certification Date'],
                ['key' => 'conciliation_history', 'label' => 'Conciliation History'],
                ['key' => 'certificate_basis', 'label' => 'Basis for Certificate'],
                ['key' => 'settlement_status', 'label' => 'Settlement / Mediation Status'],
                ['key' => 'legal_effect_statement', 'label' => 'Legal Effect Statement'],
                ['key' => 'certifying_officer', 'label' => 'Certifying Officer'],
                ['key' => 'barred_claim_description', 'label' => 'Barred Claim / Counterclaim Description'],
            ],
            'motion_for_execution', 'notice_of_execution' => [
                ['key' => 'motion_date', 'label' => 'Motion / Execution Date'],
                ['key' => 'movant_name', 'label' => 'Movant Name'],
                ['key' => 'decision_reference', 'label' => 'Decision / Settlement Reference'],
                ['key' => 'execution_grounds', 'label' => 'Grounds for Execution'],
                ['key' => 'unperformed_obligations', 'label' => 'Unperformed Obligations'],
                ['key' => 'execution_terms', 'label' => 'Execution Terms'],
                ['key' => 'executing_officer', 'label' => 'Executing Officer'],
            ],
            default => [],
        };

        $merged = [];
        foreach (array_merge($common, $variantSpecific) as $item) {
            $merged[$item['key']] = $item;
        }

        return array_values($merged);
    }

    public static function contentHtml(string $name, string $variant): string
    {
        $intro = self::intro($variant);
        $sections = implode("\n", self::sections($variant));

        return trim(sprintf(
            <<<'HTML'
<div style="font-family:'Times New Roman',serif;font-size:12pt;line-height:1.45;color:#111827;">
<p style="text-align:center;margin-bottom:0;"><strong>Republic of the Philippines</strong></p>
<p style="text-align:center;margin:0;"><strong>{{barangay_name}}</strong></p>
<p style="text-align:center;margin:0;">{{barangay_address}}, {{city_municipality}}, {{province}}</p>
<p style="text-align:center;margin-top:0;margin-bottom:2px;">Office of the Lupong Tagapamayapa</p>
<p style="text-align:center;margin-top:0;margin-bottom:14px;letter-spacing:1px;text-transform:uppercase;"><strong>Katarungang Pambarangay</strong></p>
<div style="border:1px solid #111827;padding:10px 14px 14px 14px;">
<h3 style="text-align:center;margin:4px 0 14px;text-transform:uppercase;letter-spacing:0.6px;">%s</h3>
<div style="margin-bottom:12px;border:1px solid #111827;padding:8px 10px;">
  <div style="display:flex;gap:18px;align-items:flex-start;flex-wrap:wrap;margin-bottom:6px;">
    <p style="margin:0;flex:1 1 240px;"><strong>Reference No.:</strong> {{reference_number}}</p>
    <p style="margin:0;flex:1 1 240px;"><strong>Case No.:</strong> {{case_number}}</p>
  </div>
  <div style="display:flex;gap:18px;align-items:flex-start;flex-wrap:wrap;">
    <p style="margin:0;flex:1 1 240px;"><strong>Date Issued:</strong> {{issuance_date}}</p>
    <p style="margin:0;flex:1 1 240px;"><strong>Punong Barangay:</strong> {{punong_barangay_name}}</p>
  </div>
</div>
<p>%s</p>
<h4 style="margin:14px 0 8px;text-transform:uppercase;font-size:12.5pt;">Parties and Contact Information</h4>
<div style="margin-bottom:14px;border:1px solid #111827;padding:8px 10px;">
  <p style="margin:0 0 6px 0;"><strong>Complainant:</strong> {{complainant_name}}</p>
  <p style="margin:0 0 6px 0;"><strong>Complainant Address:</strong> {{complainant_address}}</p>
  <p style="margin:0 0 6px 0;"><strong>Respondent:</strong> {{respondent_name}}</p>
  <p style="margin:0 0 6px 0;"><strong>Respondent Address:</strong> {{respondent_address}}</p>
  <p style="margin:0;"><strong>Dispute Summary:</strong> {{dispute_summary}}</p>
</div>
%s
<h4 style="margin:18px 0 8px;text-transform:uppercase;font-size:12.5pt;">Service and Record Notes</h4>
<div style="border:1px solid #111827;min-height:72px;padding:8px 10px;margin-bottom:16px;">{{service_notes}}</div>
<div style="margin-top:24px;display:flex;gap:32px;align-items:flex-start;flex-wrap:wrap;">
  <div style="flex:1 1 240px;min-width:240px;">
    <p style="margin:0 0 40px 0;">Prepared by:</p>
    <p style="margin:0;"><strong>{{prepared_by_name}}</strong><br />{{prepared_by_title}}</p>
  </div>
  <div style="flex:1 1 240px;min-width:240px;">
    <p style="margin:0 0 40px 0;">Approved / Noted by:</p>
    <p style="margin:0;"><strong>{{approved_by_name}}</strong><br />{{approved_by_title}}</p>
  </div>
</div>
</div>
</div>
HTML,
            $name,
            $intro,
            $sections
        ));
    }

    private static function intro(string $variant): string
    {
        return match ($variant) {
            'notice_to_constitute_lupon' => 'This original notice scaffold records the administrative basis, membership, and effectivity of the Lupon constitution.',
            'appointment' => 'This original appointment scaffold identifies the appointee, position, term, and administrative notes for Katarungang Pambarangay service.',
            'notice_of_appointment' => 'This original notice scaffold confirms that an appointment has been issued and communicates the role, responsibilities, and term coverage.',
            'lupon_member_list' => 'This original roster scaffold documents the composition of the Lupon and allows the barangay to maintain an updated membership list.',
            'oath_of_office' => 'This original oath scaffold captures the commitments, date, and officer acknowledgment for assuming barangay justice duties.',
            'withdrawal_of_appointment' => 'This original withdrawal scaffold records the basis, effectivity, and administrative notes for revoking or ending an appointment.',
            'complaint' => 'This original complaint scaffold records the dispute background, requested relief, and supporting statements for barangay conciliation.',
            'mediation_notice' => 'This original mediation notice scaffold states the hearing schedule, attendance instructions, and consequences for non-appearance.',
            'summons' => 'This original summons scaffold directs the recipient to appear and provides schedule, case context, and service details.',
            'notice_constitute_pangkat' => 'This original notice scaffold schedules the constitution of the Pangkat and records the selection process.',
            'notice_chosen_pangkat_member' => 'This original notice scaffold informs a chosen Pangkat member of the selection outcome and the next procedural steps.',
            'conciliation_notice' => 'This original conciliation notice scaffold states the schedule, venue, and purpose of the conciliation proceeding.',
            'subpoena' => 'This original subpoena scaffold records the appearance directive, hearing context, and requested compliance.',
            'agreement_for_arbitration' => 'This original agreement scaffold records the parties’ consent to arbitration, defined issues, and expected commitments.',
            'arbitration_award' => 'This original award scaffold records findings, terms, and compliance instructions after arbitration.',
            'amicable_settlement' => 'This original settlement scaffold captures negotiated terms, timelines, and witness acknowledgment.',
            'repudiation' => 'This original repudiation scaffold records the party’s statement, reasons, and supporting explanation regarding repudiation.',
            'notice_complainant_failure', 'notice_respondent_failure' => 'This original failure-to-appear notice scaffold states the missed setting, next hearing, and procedural consequence.',
            'certificate_to_file_action', 'certificate_file_action_lupon', 'certificate_file_action_pangkat' => 'This original certification scaffold summarizes the barangay conciliation history and the basis for filing further action.',
            'certificate_to_bar_action', 'certificate_to_bar_counterclaim' => 'This original certification scaffold records the grounds for barring the action or counterclaim and the related legal effect.',
            'motion_for_execution' => 'This original motion scaffold states the unperformed obligations, enforcement grounds, and requested execution relief.',
            'notice_motion_execution' => 'This original notice scaffold sets the hearing for the motion for execution and records service instructions.',
            'notice_of_execution' => 'This original execution notice scaffold records the enforcement directive, obligations, and implementation notes.',
            default => 'This is an original editable KP form scaffold with structured placeholders and signatory blocks.',
        };
    }

    private static function sections(string $variant): array
    {
        return match ($variant) {
            'notice_to_constitute_lupon' => [
                self::heading('Administrative Basis'),
                self::line('Resolution Number', 'resolution_number'),
                self::paragraph('Basis for Constitution', 'constitution_basis'),
                self::line('Term Coverage', 'term_coverage'),
                self::paragraph('Member Selection Notes', 'member_selection_notes'),
            ],
            'appointment', 'notice_of_appointment', 'withdrawal_of_appointment' => [
                self::heading('Appointment Information'),
                self::line('Appointee Name', 'appointee_name'),
                self::line('Appointee Address', 'appointee_address'),
                self::line('Position / Role', 'appointee_position'),
                self::line('Term Start', 'term_start'),
                self::line('Term End', 'term_end'),
                self::paragraph('Appointment Notes', 'appointment_notes'),
                ($variant === 'withdrawal_of_appointment' ? self::paragraph('Withdrawal Reason', 'withdrawal_reason') : ''),
            ],
            'lupon_member_list' => [
                self::heading('Roster Summary'),
                self::line('Number of Members', 'member_count'),
                self::paragraph('Lupon Member List', 'lupon_member_list'),
                self::paragraph('Roster Notes', 'roster_notes'),
            ],
            'oath_of_office' => [
                self::heading('Officer Information'),
                self::line('Officer Name', 'officer_name'),
                self::line('Officer Position', 'officer_position'),
                self::line('Oath Date', 'oath_date'),
                self::line('Administering Officer', 'administering_officer'),
                self::paragraph('Oath Commitments', 'oath_commitments'),
            ],
            'complaint' => [
                self::heading('Complaint Information'),
                self::line('Complaint Date', 'complaint_date'),
                self::line('Incident / Event Date', 'incident_date'),
                self::line('Incident Location', 'incident_location'),
                self::paragraph('Complaint Narrative', 'complaint_narrative'),
                self::paragraph('Supporting Facts', 'supporting_facts'),
                self::paragraph('Relief Requested', 'relief_requested'),
                self::paragraph('Witness List', 'witness_list'),
                self::paragraph('Attachments / Evidence', 'attachment_list'),
            ],
            'mediation_notice', 'conciliation_notice', 'summons', 'subpoena', 'notice_motion_execution' => [
                self::heading('Recipient and Schedule'),
                self::line('Recipient Name', 'recipient_name'),
                self::line('Recipient Address', 'recipient_address'),
                self::line('Hearing Date', 'hearing_date'),
                self::line('Hearing Time', 'hearing_time'),
                self::line('Hearing Venue', 'hearing_location'),
                self::paragraph('Purpose of Hearing', 'hearing_purpose'),
                self::paragraph('Attendance Instructions', 'attendance_instructions'),
                self::paragraph('Effect of Non-Appearance', 'nonappearance_effect'),
            ],
            'notice_constitute_pangkat', 'notice_chosen_pangkat_member' => [
                self::heading('Pangkat Constitution / Selection'),
                self::line('Selection Date', 'selection_date'),
                self::line('Selection Time', 'selection_time'),
                self::line('Selection Venue', 'selection_location'),
                self::paragraph('Selection Process Notes', 'selection_process_notes'),
                self::line('Chosen Member Name', 'chosen_member_name'),
                self::line('Chosen Member Address', 'chosen_member_address'),
            ],
            'agreement_for_arbitration' => [
                self::heading('Arbitration Agreement'),
                self::line('Agreement Date', 'agreement_date'),
                self::line('Arbitrator / Deciding Authority', 'arbitrator_name'),
                self::paragraph('Arbitration Scope', 'arbitration_scope'),
                self::paragraph('Agreed Issues', 'agreed_issues'),
                self::paragraph('Party Commitments', 'party_commitments'),
            ],
            'arbitration_award' => [
                self::heading('Award Details'),
                self::line('Award Date', 'award_date'),
                self::paragraph('Findings Summary', 'findings_summary'),
                self::paragraph('Award Terms', 'award_terms'),
                self::line('Compliance Period', 'compliance_period'),
            ],
            'amicable_settlement' => [
                self::heading('Settlement Details'),
                self::line('Settlement Date', 'settlement_date'),
                self::paragraph('Settlement Terms', 'settlement_terms'),
                self::paragraph('Obligation Schedule', 'obligation_schedule'),
                self::paragraph('Witness Names', 'witness_names'),
            ],
            'repudiation' => [
                self::heading('Repudiation Statement'),
                self::line('Repudiating Party', 'repudiating_party'),
                self::line('Repudiation Date', 'repudiation_date'),
                self::paragraph('Repudiation Reason', 'repudiation_reason'),
                self::paragraph('Supporting Statement', 'supporting_statement'),
            ],
            'notice_complainant_failure', 'notice_respondent_failure' => [
                self::heading('Failure to Appear Information'),
                self::line('Recipient Name', 'recipient_name'),
                self::line('Recipient Address', 'recipient_address'),
                self::line('Missed Hearing Date', 'missed_hearing_date'),
                self::line('Next Hearing Date', 'next_hearing_date'),
                self::line('Next Hearing Time', 'next_hearing_time'),
                self::line('Next Hearing Venue', 'next_hearing_location'),
                self::paragraph('Consequence of Failure to Appear', 'failure_consequence'),
            ],
            'certificate_to_file_action', 'certificate_file_action_lupon', 'certificate_file_action_pangkat', 'certificate_to_bar_action', 'certificate_to_bar_counterclaim' => [
                self::heading('Certification Details'),
                self::line('Certification Date', 'certification_date'),
                self::paragraph('Conciliation History', 'conciliation_history'),
                self::paragraph('Basis for Certificate', 'certificate_basis'),
                self::paragraph('Settlement / Mediation Status', 'settlement_status'),
                self::paragraph('Legal Effect Statement', 'legal_effect_statement'),
                self::line('Certifying Officer', 'certifying_officer'),
                self::paragraph('Barred Claim / Counterclaim Description', 'barred_claim_description'),
            ],
            'motion_for_execution', 'notice_of_execution' => [
                self::heading('Execution Context'),
                self::line('Motion / Execution Date', 'motion_date'),
                self::line('Movant Name', 'movant_name'),
                self::line('Decision / Settlement Reference', 'decision_reference'),
                self::paragraph('Grounds for Execution', 'execution_grounds'),
                self::paragraph('Unperformed Obligations', 'unperformed_obligations'),
                self::paragraph('Execution Terms', 'execution_terms'),
                self::line('Executing Officer', 'executing_officer'),
            ],
            default => [
                self::paragraph('General Notes', 'service_notes'),
            ],
        };
    }

    private static function heading(string $text): string
    {
        return sprintf('<h4 style="margin:16px 0 8px;padding:6px 8px;border:1px solid #111827;background:#f3f4f6;text-transform:uppercase;font-size:12pt;letter-spacing:0.4px;">%s</h4>', $text);
    }

    private static function line(string $label, string $key): string
    {
        return sprintf('<p style="margin:0;border:1px solid #111827;border-top:none;padding:6px 8px;"><strong>%s:</strong> {{%s}}</p>', $label, $key);
    }

    private static function paragraph(string $label, string $key): string
    {
        return sprintf('<div style="border:1px solid #111827;border-top:none;padding:6px 8px 10px 8px;"><p style="margin:0 0 6px 0;"><strong>%s:</strong></p><p style="margin:0;min-height:42px;">{{%s}}</p></div>', $label, $key);
    }
}
