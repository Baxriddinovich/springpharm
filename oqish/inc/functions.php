<?php
/**
 * Modul holatini aniqlash (qiymat qaytaradi)
 */
function getModuleStatus($modId)
{
    if (isset($_SESSION['reader_test_results'][$modId])) {
        return $_SESSION['reader_test_results'][$modId]['status'] === 'passed' ? 'passed' : 'failed';
    }
    if (isset($_SESSION['reader_materials_completed'][$modId]))
        return 'test_ready';
    $viewed = $_SESSION['reader_materials_viewed'][$modId] ?? [];
    if (count($viewed) > 0)
        return 'in_progress';
    return 'not_started';
}

/**
 * Modul holati uchun chiroyli badge qaytaradi
 */
function statusBadge($status)
{
    $map = [
        'not_started' => ['Boshlanmagan', 'bg-slate-700/50 text-slate-400'],
        'in_progress' => ['Jarayonda', 'bg-cyan-500/10 text-cyan-400 border border-cyan-500/20'],
        'test_ready' => ['Testga tayyor', 'bg-amber-500/10 text-amber-400 border border-amber-500/20'],
        'passed' => ["O'tdi", 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20'],
        'failed' => ["O'tmadi", 'bg-red-500/10 text-red-400 border border-red-500/20'],
    ];
    $d = $map[$status] ?? $map['not_started'];
    return '<span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-medium ' . $d[1] . '">' . $d[0] . '</span>';
}

/**
 * Modul holatini aniqlash (icon qaytaradi)
 */
function statusIcon($status)
{
    switch ($status) {
        case 'passed':
            return '<svg class="w-4 h-4 text-emerald-400" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>';
        case 'in_progress':
        case 'test_ready':
            return '<div class="w-2.5 h-2.5 rounded-full bg-amber-400 shadow-[0_0_8px_rgba(251,191,36,0.5)]"></div>';
        default:
            return '<div class="w-2.5 h-2.5 rounded-full bg-slate-600"></div>';
    }
}

/**
 * Fayl turi bo'yicha icon rangini qaytaradi
 */
function fileIconClass($type)
{
    $type = strtolower($type);
    if (strpos($type, 'pdf') !== false)
        return 'text-red-400';
    if (strpos($type, 'word') !== false || strpos($type, 'docx') !== false || strpos($type, 'document') !== false)
        return 'text-blue-400';
    if (strpos($type, 'video') !== false || strpos($type, 'mp4') !== false)
        return 'text-purple-400';
    if (strpos($type, 'image') !== false)
        return 'text-emerald-400';
    if (strpos($type, 'presentation') !== false || strpos($type, 'pptx') !== false)
        return 'text-orange-400';
    return 'text-slate-400';
}
