<?php

namespace App\Support\Reports;

final class ReportCatalog
{
    public const CATEGORIES = [
        'resident' => 'Resident Reports',
        'certificate' => 'Certificate Reports',
        'lupon' => 'Lupon Reports',
        'blotter' => 'Blotter Reports',
        'financial' => 'Financial Reports',
    ];

    public const REPORTS = [
        'resident.masterlist' => [
            'category' => 'resident', 'label' => 'Masterlist of Residents', 'type' => 'list',
            'filters' => ['purok_id', 'sex', 'resident_status', 'search'],
            'columns' => [
                ['key' => 'resident_number', 'label' => 'Resident #'],
                ['key' => 'full_name', 'label' => 'Full Name'],
                ['key' => 'sex', 'label' => 'Sex'],
                ['key' => 'age', 'label' => 'Age'],
                ['key' => 'civil_status', 'label' => 'Civil Status'],
                ['key' => 'purok', 'label' => 'Purok'],
                ['key' => 'household_number', 'label' => 'Household #'],
                ['key' => 'mobile_number', 'label' => 'Mobile #'],
                ['key' => 'address', 'label' => 'Address'],
                ['key' => 'status', 'label' => 'Status'],
            ],
        ],
        'resident.population_report' => [
            'category' => 'resident', 'label' => 'Population Report', 'type' => 'summary',
            'filters' => ['purok_id'],
            'columns' => [['key' => 'label', 'label' => 'Metric'], ['key' => 'value', 'label' => 'Count']],
        ],
        'resident.population_by_purok' => [
            'category' => 'resident', 'label' => 'Population by Purok', 'type' => 'summary',
            'filters' => ['sex'],
            'columns' => [['key' => 'label', 'label' => 'Purok'], ['key' => 'male', 'label' => 'Male'], ['key' => 'female', 'label' => 'Female'], ['key' => 'value', 'label' => 'Total']],
        ],
        'resident.population_by_gender' => [
            'category' => 'resident', 'label' => 'Population by Gender', 'type' => 'summary',
            'filters' => ['purok_id'],
            'columns' => [['key' => 'label', 'label' => 'Sex'], ['key' => 'value', 'label' => 'Count'], ['key' => 'percent', 'label' => '%']],
        ],
        'resident.population_by_age' => [
            'category' => 'resident', 'label' => 'Population by Age', 'type' => 'summary',
            'filters' => ['purok_id'],
            'columns' => [['key' => 'label', 'label' => 'Age Bracket'], ['key' => 'value', 'label' => 'Count']],
        ],
        'resident.senior_citizens' => [
            'category' => 'resident', 'label' => 'Senior Citizen List', 'type' => 'list',
            'filters' => ['purok_id', 'sex', 'search'],
            'columns' => [
                ['key' => 'resident_number', 'label' => 'Resident #'], ['key' => 'full_name', 'label' => 'Full Name'],
                ['key' => 'sex', 'label' => 'Sex'], ['key' => 'age', 'label' => 'Age'],
                ['key' => 'purok', 'label' => 'Purok'], ['key' => 'address', 'label' => 'Address'], ['key' => 'mobile_number', 'label' => 'Mobile #'],
            ],
        ],
        'resident.pwd_list' => [
            'category' => 'resident', 'label' => 'PWD List', 'type' => 'list',
            'filters' => ['purok_id', 'sex', 'search'],
            'columns' => [
                ['key' => 'resident_number', 'label' => 'Resident #'], ['key' => 'full_name', 'label' => 'Full Name'],
                ['key' => 'sex', 'label' => 'Sex'], ['key' => 'age', 'label' => 'Age'],
                ['key' => 'purok', 'label' => 'Purok'], ['key' => 'address', 'label' => 'Address'], ['key' => 'mobile_number', 'label' => 'Mobile #'],
            ],
        ],
        'resident.voters_list' => [
            'category' => 'resident', 'label' => 'Voter-related List', 'type' => 'list',
            'filters' => ['purok_id', 'sex', 'search'],
            'columns' => [
                ['key' => 'resident_number', 'label' => 'Resident #'], ['key' => 'full_name', 'label' => 'Full Name'],
                ['key' => 'sex', 'label' => 'Sex'], ['key' => 'age', 'label' => 'Age'],
                ['key' => 'purok', 'label' => 'Purok'], ['key' => 'address', 'label' => 'Address'],
            ],
        ],
        'resident.household_report' => [
            'category' => 'resident', 'label' => 'Household Report', 'type' => 'list',
            'filters' => ['purok_id', 'search'],
            'columns' => [
                ['key' => 'household_number', 'label' => 'Household #'], ['key' => 'name', 'label' => 'Household Name'],
                ['key' => 'purok', 'label' => 'Purok'], ['key' => 'address', 'label' => 'Address'],
                ['key' => 'head', 'label' => 'Head of Household'], ['key' => 'member_count', 'label' => 'Members'],
            ],
        ],

        'certificate.issued' => [
            'category' => 'certificate', 'label' => 'Certificates Issued', 'type' => 'list',
            'filters' => ['date_from', 'date_to', 'service_type_id', 'search'],
            'columns' => [
                ['key' => 'request_number', 'label' => 'Request #'], ['key' => 'resident', 'label' => 'Resident'],
                ['key' => 'service_type', 'label' => 'Service Type'], ['key' => 'requested_at', 'label' => 'Requested'],
                ['key' => 'released_at', 'label' => 'Released'],
            ],
        ],
        'certificate.pending_requests' => [
            'category' => 'certificate', 'label' => 'Pending Requests', 'type' => 'list',
            'filters' => ['service_type_id', 'search'],
            'columns' => [
                ['key' => 'request_number', 'label' => 'Request #'], ['key' => 'resident', 'label' => 'Resident'],
                ['key' => 'service_type', 'label' => 'Service Type'], ['key' => 'status', 'label' => 'Status'],
                ['key' => 'requested_at', 'label' => 'Requested'],
            ],
        ],
        'certificate.requests_by_type' => [
            'category' => 'certificate', 'label' => 'Requests by Type', 'type' => 'summary',
            'filters' => ['date_from', 'date_to'],
            'columns' => [['key' => 'label', 'label' => 'Service Type'], ['key' => 'value', 'label' => 'Total'], ['key' => 'released', 'label' => 'Released'], ['key' => 'pending', 'label' => 'Pending']],
        ],
        'certificate.daily_transactions' => [
            'category' => 'certificate', 'label' => 'Daily Transactions', 'type' => 'list',
            'filters' => ['date_from', 'date_to', 'service_type_id'],
            'columns' => [
                ['key' => 'request_number', 'label' => 'Request #'], ['key' => 'resident', 'label' => 'Resident'],
                ['key' => 'service_type', 'label' => 'Service Type'], ['key' => 'status', 'label' => 'Status'],
                ['key' => 'requested_at', 'label' => 'Requested'],
            ],
        ],
        'certificate.monthly_transactions' => [
            'category' => 'certificate', 'label' => 'Monthly Transactions', 'type' => 'summary',
            'filters' => ['date_from', 'date_to'],
            'columns' => [['key' => 'label', 'label' => 'Month'], ['key' => 'value', 'label' => 'Total Requests'], ['key' => 'released', 'label' => 'Released']],
        ],
        'certificate.released_documents' => [
            'category' => 'certificate', 'label' => 'Released Documents', 'type' => 'list',
            'filters' => ['date_from', 'date_to', 'service_type_id'],
            'columns' => [
                ['key' => 'request_number', 'label' => 'Request #'], ['key' => 'service_type', 'label' => 'Service Type'],
                ['key' => 'released_to', 'label' => 'Released To'], ['key' => 'or_number', 'label' => 'OR #'],
                ['key' => 'signatory', 'label' => 'Signatory'], ['key' => 'released_at', 'label' => 'Released'],
            ],
        ],

        'lupon.cases_filed' => [
            'category' => 'lupon', 'label' => 'Cases Filed', 'type' => 'list',
            'filters' => ['date_from', 'date_to', 'search'],
            'columns' => [
                ['key' => 'case_number', 'label' => 'Case #'], ['key' => 'complainant', 'label' => 'Complainant'],
                ['key' => 'respondent', 'label' => 'Respondent'], ['key' => 'nature', 'label' => 'Nature'],
                ['key' => 'status', 'label' => 'Status'], ['key' => 'date_filed', 'label' => 'Date Filed'],
            ],
        ],
        'lupon.cases_settled' => [
            'category' => 'lupon', 'label' => 'Cases Settled', 'type' => 'list',
            'filters' => ['date_from', 'date_to', 'search'],
            'columns' => [
                ['key' => 'case_number', 'label' => 'Case #'], ['key' => 'complainant', 'label' => 'Complainant'],
                ['key' => 'respondent', 'label' => 'Respondent'], ['key' => 'nature', 'label' => 'Nature'],
                ['key' => 'settlement_date', 'label' => 'Settlement Date'],
            ],
        ],
        'lupon.cases_pending' => [
            'category' => 'lupon', 'label' => 'Cases Pending', 'type' => 'list',
            'filters' => ['search'],
            'columns' => [
                ['key' => 'case_number', 'label' => 'Case #'], ['key' => 'complainant', 'label' => 'Complainant'],
                ['key' => 'respondent', 'label' => 'Respondent'], ['key' => 'nature', 'label' => 'Nature'],
                ['key' => 'date_filed', 'label' => 'Date Filed'],
            ],
        ],
        'lupon.unsettled_cases' => [
            'category' => 'lupon', 'label' => 'Unsettled Cases', 'type' => 'list',
            'filters' => ['search'],
            'columns' => [
                ['key' => 'case_number', 'label' => 'Case #'], ['key' => 'complainant', 'label' => 'Complainant'],
                ['key' => 'respondent', 'label' => 'Respondent'], ['key' => 'nature', 'label' => 'Nature'],
                ['key' => 'certificate_issued_at', 'label' => 'CFA Issued'],
            ],
        ],
        'lupon.cfa_issued' => [
            'category' => 'lupon', 'label' => 'CFA Issued', 'type' => 'list',
            'filters' => ['date_from', 'date_to', 'search'],
            'columns' => [
                ['key' => 'case_number', 'label' => 'Case #'], ['key' => 'certificate_number', 'label' => 'CFA #'],
                ['key' => 'complainant', 'label' => 'Complainant'], ['key' => 'respondent', 'label' => 'Respondent'],
                ['key' => 'certificate_issued_at', 'label' => 'Issued'],
            ],
        ],
        'lupon.cases_by_nature' => [
            'category' => 'lupon', 'label' => 'Cases by Nature', 'type' => 'summary',
            'filters' => ['date_from', 'date_to'],
            'columns' => [['key' => 'label', 'label' => 'Nature'], ['key' => 'value', 'label' => 'Total']],
        ],
        'lupon.cases_by_month' => [
            'category' => 'lupon', 'label' => 'Cases by Month', 'type' => 'summary',
            'filters' => ['date_from', 'date_to'],
            'columns' => [['key' => 'label', 'label' => 'Month'], ['key' => 'value', 'label' => 'Cases Filed']],
        ],
        'lupon.settlement_rate' => [
            'category' => 'lupon', 'label' => 'Settlement Rate', 'type' => 'summary',
            'filters' => ['date_from', 'date_to'],
            'columns' => [['key' => 'label', 'label' => 'Nature'], ['key' => 'total', 'label' => 'Total'], ['key' => 'settled', 'label' => 'Settled'], ['key' => 'value', 'label' => 'Settlement Rate %']],
        ],
        'lupon.hearing_statistics' => [
            'category' => 'lupon', 'label' => 'Hearing Statistics', 'type' => 'summary',
            'filters' => ['date_from', 'date_to'],
            'columns' => [['key' => 'label', 'label' => 'Metric'], ['key' => 'value', 'label' => 'Count']],
        ],
        'lupon.compliance_summary' => [
            'category' => 'lupon', 'label' => 'KP Compliance Summary (DILG)', 'type' => 'summary',
            'filters' => ['date_from', 'date_to'],
            'columns' => [['key' => 'label', 'label' => 'Metric'], ['key' => 'value', 'label' => 'Count']],
        ],

        'blotter.entries' => [
            'category' => 'blotter', 'label' => 'Blotter Entries', 'type' => 'list',
            'filters' => ['date_from', 'date_to', 'purok_id', 'blotter_status', 'search'],
            'columns' => [
                ['key' => 'blotter_number', 'label' => 'Blotter #'], ['key' => 'complainant', 'label' => 'Complainant'],
                ['key' => 'respondent', 'label' => 'Respondent'], ['key' => 'incident_type', 'label' => 'Incident Type'],
                ['key' => 'status', 'label' => 'Status'], ['key' => 'incident_date', 'label' => 'Incident Date'],
            ],
        ],
        'blotter.incidents_by_type' => [
            'category' => 'blotter', 'label' => 'Incidents by Type', 'type' => 'summary',
            'filters' => ['date_from', 'date_to'],
            'columns' => [['key' => 'label', 'label' => 'Incident Type'], ['key' => 'value', 'label' => 'Total']],
        ],
        'blotter.incidents_by_purok' => [
            'category' => 'blotter', 'label' => 'Incidents by Purok', 'type' => 'summary',
            'filters' => ['date_from', 'date_to'],
            'columns' => [['key' => 'label', 'label' => 'Purok'], ['key' => 'value', 'label' => 'Total']],
        ],
        'blotter.monthly_incidents' => [
            'category' => 'blotter', 'label' => 'Monthly Incidents', 'type' => 'summary',
            'filters' => ['date_from', 'date_to'],
            'columns' => [['key' => 'label', 'label' => 'Month'], ['key' => 'value', 'label' => 'Total']],
        ],
        'blotter.resolved_cases' => [
            'category' => 'blotter', 'label' => 'Resolved Cases', 'type' => 'list',
            'filters' => ['date_from', 'date_to', 'search'],
            'columns' => [
                ['key' => 'blotter_number', 'label' => 'Blotter #'], ['key' => 'complainant', 'label' => 'Complainant'],
                ['key' => 'respondent', 'label' => 'Respondent'], ['key' => 'incident_type', 'label' => 'Incident Type'],
                ['key' => 'closed_at', 'label' => 'Resolved On'],
            ],
        ],
        'blotter.referred_cases' => [
            'category' => 'blotter', 'label' => 'Referred Cases', 'type' => 'list',
            'filters' => ['date_from', 'date_to', 'search'],
            'columns' => [
                ['key' => 'blotter_number', 'label' => 'Blotter #'], ['key' => 'complainant', 'label' => 'Complainant'],
                ['key' => 'respondent', 'label' => 'Respondent'], ['key' => 'referred_reason', 'label' => 'Referred Reason'],
                ['key' => 'referred_at', 'label' => 'Referred On'],
            ],
        ],

        'financial.daily_collections' => [
            'category' => 'financial', 'label' => 'Daily Collections', 'type' => 'list',
            'filters' => ['date_from', 'date_to'],
            'columns' => [
                ['key' => 'transaction_number', 'label' => 'Transaction #'], ['key' => 'payor_name', 'label' => 'Payor'],
                ['key' => 'transaction_type', 'label' => 'Type'], ['key' => 'amount', 'label' => 'Amount'],
                ['key' => 'or_number', 'label' => 'OR #'], ['key' => 'paid_at', 'label' => 'Paid On'],
            ],
        ],
        'financial.monthly_collections' => [
            'category' => 'financial', 'label' => 'Monthly Collections', 'type' => 'summary',
            'filters' => ['date_from', 'date_to'],
            'columns' => [['key' => 'label', 'label' => 'Month'], ['key' => 'value', 'label' => 'Total Collected']],
        ],
        'financial.transactions_by_service' => [
            'category' => 'financial', 'label' => 'Transactions by Service', 'type' => 'summary',
            'filters' => ['date_from', 'date_to'],
            'columns' => [['key' => 'label', 'label' => 'Transaction Type'], ['key' => 'count', 'label' => 'Count'], ['key' => 'value', 'label' => 'Total Amount']],
        ],
        'financial.payment_summary' => [
            'category' => 'financial', 'label' => 'Payment Summary', 'type' => 'summary',
            'filters' => ['date_from', 'date_to'],
            'columns' => [['key' => 'label', 'label' => 'Status'], ['key' => 'count', 'label' => 'Count'], ['key' => 'value', 'label' => 'Total Amount']],
        ],
    ];

    private function __construct()
    {
    }
}
