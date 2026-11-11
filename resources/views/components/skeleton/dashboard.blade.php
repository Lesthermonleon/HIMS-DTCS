{{--
    Skeleton: Full Dashboard Page
    Usage: <x-skeleton.dashboard />
--}}
<div role="status" aria-label="Loading dashboard" aria-busy="true">

    {{-- Welcome Header Banner --}}
    <div class="mb-3 d-flex align-items-center gap-3">
        <div class="sk sk-circle" style="width:42px;height:42px;border-radius:.65rem;flex-shrink:0;"></div>
        <div>
            <div class="sk sk-lg mb-1" style="width:180px;border-radius:4px;"></div>
            <div class="sk sk-sm" style="width:230px;border-radius:4px;"></div>
        </div>
    </div>

    {{-- 6 Summary Stat Cards Grid --}}
    <div class="row g-3 mb-4">
        @for ($i = 0; $i < 6; $i++)
        <div class="col-sm-6 col-xl-2">
            <div class="card border-0 shadow-sm h-100 p-3" style="border-radius: .75rem;">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <div class="sk sk-xs" style="width:70px;border-radius:4px;"></div>
                    <div class="sk sk-circle" style="width:32px;height:32px;"></div>
                </div>
                <div class="sk sk-xl mb-2" style="width:60px;height:28px;border-radius:6px;"></div>
                <div class="sk sk-xs" style="width:80px;border-radius:4px;"></div>
            </div>
        </div>
        @endfor
    </div>

    {{-- User & Role Distribution Overview Card --}}
    <div class="card border-0 shadow-sm mb-4" style="border-radius: .75rem;">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center border-bottom">
            <div class="sk sk-md" style="width:240px;border-radius:4px;"></div>
            <div class="sk sk-sm sk-rounded" style="width:110px;height:30px;"></div>
        </div>
        <div class="card-body">
            <div class="row g-3">
                @for ($r = 0; $r < 4; $r++)
                <div class="col-sm-6 col-md-4 col-lg-3">
                    <div class="p-3 border rounded-3 d-flex align-items-center justify-content-between">
                        <div>
                            <div class="sk sk-sm mb-1" style="width:90px;border-radius:4px;"></div>
                            <div class="sk sk-xs" style="width:110px;border-radius:4px;"></div>
                        </div>
                        <div class="sk sk-circle" style="width:28px;height:28px;"></div>
                    </div>
                </div>
                @endfor
            </div>
        </div>
    </div>

    {{-- Security Alerts & Recent System Activity Row --}}
    <div class="row g-4 mb-4">
        {{-- System Alerts --}}
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm h-100" style="border-radius: .75rem;">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center border-bottom">
                    <div class="sk sk-md" style="width:180px;border-radius:4px;"></div>
                    <div class="sk sk-xs sk-pill" style="width:70px;height:22px;"></div>
                </div>
                <div class="card-body p-3 d-flex flex-column gap-3">
                    @for ($a = 0; $a < 3; $a++)
                    <div class="d-flex align-items-start gap-3 p-2 border-bottom pb-3">
                        <div class="sk sk-circle" style="width:36px;height:36px;flex-shrink:0;"></div>
                        <div class="w-100">
                            <div class="sk sk-sm mb-1" style="width:140px;border-radius:4px;"></div>
                            <div class="sk sk-xs mb-2" style="width:90%;border-radius:4px;"></div>
                            <div class="sk sk-xs" style="width:80px;height:24px;border-radius:4px;"></div>
                        </div>
                    </div>
                    @endfor
                </div>
            </div>
        </div>

        {{-- Recent System Activity Log --}}
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm h-100" style="border-radius: .75rem;">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center border-bottom">
                    <div class="sk sk-md" style="width:180px;border-radius:4px;"></div>
                    <div class="sk sk-sm sk-rounded" style="width:120px;height:30px;"></div>
                </div>
                <div class="card-body p-0 overflow-hidden">
                    <div class="skeleton-table-header">
                        <div class="sk sk-xs" style="width:70px;"></div>
                        <div class="sk sk-xs" style="width:60px;"></div>
                        <div class="sk sk-xs" style="width:60px;"></div>
                        <div class="sk sk-xs" style="width:120px;"></div>
                        <div class="sk sk-xs" style="width:80px;"></div>
                    </div>
                    @for ($row = 0; $row < 4; $row++)
                    <div class="skeleton-table-row">
                        <div class="sk sk-sm" style="width:80px;"></div>
                        <div class="sk sk-sm sk-pill" style="width:60px;"></div>
                        <div class="sk sk-sm sk-pill" style="width:60px;"></div>
                        <div class="sk sk-sm" style="width:140px;"></div>
                        <div class="sk sk-xs" style="width:70px;"></div>
                    </div>
                    @endfor
                </div>
            </div>
        </div>
    </div>

    {{-- Clinical Module Operational Overview --}}
    <div class="card border-0 shadow-sm mb-4" style="border-radius: .75rem;">
        <div class="card-header bg-white py-3 border-bottom">
            <div class="sk sk-md" style="width:260px;border-radius:4px;"></div>
        </div>
        <div class="card-body">
            <div class="row g-3">
                @for ($m = 0; $m < 5; $m++)
                <div class="col-md-6 col-lg">
                    <div class="card border border-light-subtle h-100 p-3">
                        <div class="d-flex align-items-center mb-2 gap-2">
                            <div class="sk sk-circle" style="width:32px;height:32px;"></div>
                            <div class="sk sk-sm" style="width:100px;"></div>
                        </div>
                        <div class="sk sk-xs mb-3" style="width:120px;"></div>
                        <div class="sk sk-md w-100" style="height:32px;border-radius:6px;"></div>
                    </div>
                </div>
                @endfor
            </div>
        </div>
    </div>

</div>
