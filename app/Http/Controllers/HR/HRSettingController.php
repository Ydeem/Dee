<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\HR\HRSetting;
use Illuminate\Http\Request;

class HRSettingController extends Controller
{
    public function index()
    {
        $settings = HRSetting::query()
            ->orderBy('group')
            ->orderBy('id')
            ->get()
            ->groupBy('group')
            ->map(fn ($group) => $group->keyBy('key'));

        return response()->json(['settings' => $settings]);
    }

    public function update(Request $request)
    {
        $request->validate([
            'settings' => 'required|array',
            'settings.*' => 'nullable|string',
        ]);

        foreach ($request->settings as $key => $value) {
            HRSetting::where('key', $key)->update(['value' => $value]);
        }

        return response()->json(['message' => 'Settings saved successfully.']);
    }

    public function value(string $key)
    {
        $setting = HRSetting::where('key', $key)->firstOrFail();

        return response()->json(['value' => $setting->value]);
    }
}
