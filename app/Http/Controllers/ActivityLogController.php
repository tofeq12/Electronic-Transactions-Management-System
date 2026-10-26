<?php

namespace App\Http\Controllers;

/**
 * سجل النشاط: محمي ببوابة can:activity.view (المدير فقط) على المسار،
 * وبيانات السجل تُحقن عبر حِمل ItqanData المشترط بنفس القدرة.
 */
class ActivityLogController extends Controller
{
    /**
     * Activity log page — gated by the activity.view ability (مدير only,
     * mirroring access.js) and injected through the shared ITQAN payload.
     */
    public function index()
    {
        return $this->page('pages.activity-log');
    }
}
