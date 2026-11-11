<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * DepartmentController — Super Admin DTCS Subsystem Departments & Resource Monitor.
 *
 * Provides real-time operational monitoring of the 5 predefined DTCS clinical departments
 * (LIS, RIS, PMS, SORS, DNMS), their active staff allocations, and live clinical request volumes.
 * Predefined architectural subsystem mappings; ad-hoc department creation is not permitted.
 */
class DepartmentController extends Controller
{
    /**
     * Standard DTCS Subsystem Department Catalog (LIS, RIS, PMS, SORS, DNMS)
     */
    protected function getStandardDepartments(): array
    {
        return [
            [
                'name'        => 'Laboratory',
                'code'        => 'LIS-LAB',
                'module'      => 'LIS',
                'type'        => 'Diagnostic',
                'description' => 'Laboratory Information System — Hematology, clinical chemistry, microbiology, urinalysis, and blood banking',
                'model'       => \App\Models\LabRequest::class,
            ],
            [
                'name'        => 'Radiology',
                'code'        => 'RIS-RAD',
                'module'      => 'RIS',
                'type'        => 'Diagnostic',
                'description' => 'Radiology & Imaging System — X-Ray, CT scan, MRI, ultrasonography, mammography, and fluoroscopy imaging',
                'model'       => \App\Models\RadiologyRequest::class,
            ],
            [
                'name'        => 'Pharmacy',
                'code'        => 'PMS-PHR',
                'module'      => 'PMS',
                'type'        => 'Support / Treatment',
                'description' => 'Pharmacy Management System — Prescription verification, medication dispensing, and pharmaceutical care',
                'model'       => \App\Models\Prescription::class,
            ],
            [
                'name'        => 'Operating Room',
                'code'        => 'SORS-OR',
                'module'      => 'SORS',
                'type'        => 'Treatment',
                'description' => 'Surgery & Operating Room Scheduler — Operating room suite allocation, procedure scheduling, and surgical teams',
                'model'       => \App\Models\SurgeryRequest::class,
            ],
            [
                'name'        => 'Nutrition & Dietetics',
                'code'        => 'DNMS-NUT',
                'module'      => 'DNMS',
                'type'        => 'Treatment / Clinical',
                'description' => 'Diet & Nutrition Management System — Inpatient therapeutic diet formulation, meal scheduling, and nutritional planning',
                'model'       => \App\Models\DietRequest::class,
            ],
        ];
    }

    public function index(): View
    {
        $standardDepts = $this->getStandardDepartments();

        // Calculate active user assignments per DTCS department
        $userCounts = User::select('department', DB::raw('count(*) as total'))
            ->whereNotNull('department')
            ->groupBy('department')
            ->pluck('total', 'department');

        $departments = collect($standardDepts)->map(function ($dept) use ($userCounts) {
            $name = $dept['name'];
            $dept['staff_count'] = $userCounts->get($name, 0);
            $dept['request_count'] = $dept['model']::count();
            $dept['status'] = 'Active';
            return $dept;
        });

        return view('super_admin.departments.index', compact('departments'));
    }
}
