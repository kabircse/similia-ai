<?php

namespace App\Http\Controllers\Api\Concerns;

use App\Models\Patient;
use App\Models\PatientVisit;
use App\Models\User;
use Illuminate\Http\Request;

trait ResolvesDoctorOwnership
{
    protected function ownerDoctorIdForPatient(Request $request, Patient $patient): int
    {
        return $request->user()->role === 'admin'
            ? $patient->doctor_id
            : $request->user()->id;
    }

    protected function ownerDoctorIdForVisit(Request $request, PatientVisit $visit): int
    {
        return $request->user()->role === 'admin'
            ? $visit->doctor_id
            : $request->user()->id;
    }

    protected function ownerDoctorForVisit(Request $request, PatientVisit $visit): User
    {
        if ($request->user()->role !== 'admin') {
            return $request->user();
        }

        return User::findOrFail($visit->doctor_id);
    }
}
