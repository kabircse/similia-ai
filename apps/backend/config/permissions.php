<?php

return [
    'roles' => [
        'admin' => [
            'view_dashboard',
            'manage_patients',
            'manage_visits',
            'manage_rubrics',
            'run_repertorization',
            'compare_materia_medica',
            'manage_prescriptions',
            'manage_fees',
            'print_documents',
            'view_activity_logs',
            'manage_clinic_settings',
            'manage_users',
        ],

        'doctor' => [
            'view_dashboard',
            'manage_patients',
            'manage_visits',
            'manage_rubrics',
            'run_repertorization',
            'compare_materia_medica',
            'manage_prescriptions',
            'manage_fees',
            'print_documents',
            'view_activity_logs',
            'manage_clinic_settings',
        ],

        'assistant' => [
            'view_dashboard',
            'manage_patients',
            'manage_visits',
            'manage_fees',
            'print_documents',
        ],
    ],
];
