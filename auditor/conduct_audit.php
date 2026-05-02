<?php
// conduct_audit.php - Audit o'tkazish (AVTOMATIK NOM BILAN VA MODAL LI)
require_once '../db.php';
requireLogin();

 $user = getCurrentUser();
 $auditId = (int)($_GET['id'] ?? 0);

// Audit ma'lumotlari
 $stmt = $pdo->prepare("SELECT a.*, s.name as site_name FROM audits a JOIN sites s ON a.site_id = s.id WHERE a.id = ?");
 $stmt->execute([$auditId]);
 $audit = $stmt->fetch();

if (!$audit) die("Audit topilmadi!");

// Huquqni tekshirish
 $hasAccess = false;
if (in_array($user['role'], ['super_admin', 'bosh_auditor'])) {
    $hasAccess = true;
} else {
    $stmt = $pdo->prepare("SELECT 1 FROM audit_assignments WHERE audit_id = ? AND auditor_id = ?");
    $stmt->execute([$auditId, $user['id']]);
    $hasAccess = (bool)$stmt->fetch();
}
if (!$hasAccess) die("Sizda bu auditga kirish huquqi yo'q!");

// ⭐ Severities nomlarini aniqlash
 $severities = [
    1 => ['name' => 'Jiddiy bo\'lmagan', 'color' => 'text-emerald-400', 'bg' => 'bg-emerald-500/10', 'border' => 'border-emerald-500'],
    2 => ['name' => 'Jiddiy', 'color' => 'text-amber-400', 'bg' => 'bg-amber-500/10', 'border' => 'border-amber-500'],
    3 => ['name' => 'O\'ta jiddiy', 'color' => 'text-red-400', 'bg' => 'bg-red-500/10', 'border' => 'border-red-500']
];

// ------------------- AJAX HANDLER (Avtomatik NC yaratish) -------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'auto_create_nc') {
    header('Content-Type: application/json');
    $qId = (int)$_POST['question_id'];
    
    try {
        $pdo->beginTransaction();

        // 1. Javobni saqlash (YO'Q)
        $checkAns = $pdo->prepare("SELECT id FROM audit_answers WHERE audit_id = ? AND question_id = ?");
        $checkAns->execute([$auditId, $qId]);
        $existingAns = $checkAns->fetch();
        
        $answerId = 0;

        if ($existingAns) {
            $upd = $pdo->prepare("UPDATE audit_answers SET answer = 'yoq', auditor_id = ? WHERE id = ?");
            $upd->execute([$user['id'], $existingAns['id']]);
            $answerId = $existingAns['id'];
        } else {
            $ins = $pdo->prepare("INSERT INTO audit_answers (audit_id, question_id, answer, auditor_id) VALUES (?, ?, 'yoq', ?)");
            $ins->execute([$auditId, $qId, $user['id']]);
            $answerId = $pdo->lastInsertId();
        }

        // 2. NC allaqachon bormi?
        $checkNc = $pdo->prepare("SELECT id, nc_number, nc_code FROM non_conformities WHERE audit_id = ? AND question_id = ?");
        $checkNc->execute([$auditId, $qId]);
        $existingNc = $checkNc->fetch();
        
        if (!$existingNc) {
            // 3. Yangi NC uchun tartib raqamni aniqlash (nc_number)
            // Faqat shu audit ichidagi eng katta raqamni olamiz
            $lastNc = $pdo->prepare("SELECT MAX(nc_number) as max_num FROM non_conformities WHERE audit_id = ?");
            $lastNc->execute([$auditId]);
            $last = $lastNc->fetch();
            
            $nextNum = 1;
            if ($last && $last['max_num']) {
                $nextNum = (int)$last['max_num'] + 1;
            }
            
            // 4. NC ma'lumotlarini tayyorlash
            //nc_code: Unikal bo'lishi uchun audit kodini qo'shamiz (masalan: NC-A001-1)
            $newCode = "NC-" . $audit['audit_code'] . "-" . $nextNum;
            
            // description: Siz so'ragan "Nomuvofiqlik X" formati
            $autoDescription = "Nomuvofiqlik " . $nextNum;
            
            // 5. NC yaratish
            $insNc = $pdo->prepare("
                INSERT INTO non_conformities 
                (audit_id, question_id, answer_id, nc_code, nc_number, severity_id, description, created_by) 
                VALUES (?, ?, ?, ?, ?, 1, ?, ?)
            ");
            // Default severity_id = 1 (Jiddiy bo'lmagan)
            $insNc->execute([$auditId, $qId, $answerId, $newCode, $nextNum, $autoDescription, $user['id']]);
            
            $newNcId = $pdo->lastInsertId();
            
            $pdo->commit();
            
            // Frontendga qaytariladigan ma'lumotlar
            echo json_encode([
                'success' => true, 
                'nc_code' => $newCode, 
                'nc_id' => $newNcId,
                'nc_number' => $nextNum,
                'description' => $autoDescription,
                'severity_id' => 1
            ]);
        } else {
            // Agar NC mavjud bo'lsa, faqat javobni yangilab, mavjud NC haqida xabar beramiz
            $pdo->commit();
            echo json_encode([
                'success' => false, 
                'message' => 'Nomuvofiqlik allaqachon mavjud',
                'nc_id' => $existingNc['id'],
                'nc_number' => $existingNc['nc_number'],
                'nc_code' => $existingNc['nc_code']
            ]);
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}
// -------------------------------------------------------------------------------

// Bo'limlar
// 1. AVVAL bo'limlarni oling
if (in_array($user['role'], ['super_admin', 'bosh_auditor'])) {
    $sections = $pdo->query("SELECT * FROM gmp_sections ORDER BY sort_order")->fetchAll();
} else {
    $stmt = $pdo->prepare("
        SELECT gs.* FROM gmp_sections gs
        JOIN audit_assignments aa ON aa.section_id = gs.id
        WHERE aa.audit_id = ? AND aa.auditor_id = ?
        ORDER BY gs.sort_order
    ");
    $stmt->execute([$auditId, $user['id']]);
    $sections = $stmt->fetchAll();
}

// 2. KEYIN selectedSection aniqlang
$selectedSection = (int)($_GET['section'] ?? ($sections[0]['id'] ?? 0));
// Savollarni olish
 $stmt = $pdo->prepare("
    SELECT cq.*, 
           COALESCE(aa.answer, 'na') as answer,
           aa.comment,
           aa.image_path,
           nc.id as nc_id,
           nc.nc_code,
           nc.nc_number,
           nc.severity_id,
           nc.description as nc_description
    FROM checklist_questions cq
    LEFT JOIN audit_answers aa ON aa.question_id = cq.id AND aa.audit_id = ?
    LEFT JOIN non_conformities nc ON nc.question_id = cq.id AND nc.audit_id = ?
    WHERE cq.section_id = ? AND cq.is_active = 1
    ORDER BY cq.sort_order
");
 $stmt->execute([$auditId, $auditId, $selectedSection]);
 $questions = $stmt->fetchAll();

// Progress
 $totalQ = $pdo->query("SELECT COUNT(*) FROM checklist_questions WHERE is_active = 1")->fetchColumn();
 $answeredQ = $pdo->prepare("SELECT COUNT(*) FROM audit_answers WHERE audit_id = ? AND answer != 'na'");
 $answeredQ->execute([$auditId]);
 $progress = $totalQ > 0 ? round(($answeredQ->fetchColumn() / $totalQ) * 100, 1) : 0;
?>
<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Audit #<?php echo $audit['audit_code']; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { background: #0a0f1a; font-family: 'Inter', sans-serif; }
        .ans-group { display: flex; gap: 0.5rem; }
        .ans-btn {
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            font-size: 0.875rem;
            font-weight: 500;
            border: 1px solid #334155;
            background: rgba(30, 41, 59, 0.5);
            color: #94a3b8;
            cursor: pointer;
            transition: all 0.2s;
        }
        .ans-btn:hover { border-color: #06b6d4; color: #fff; }
        
        .ans-btn.active-ha { background: rgba(16, 185, 129, 0.2); border-color: #10b981; color: #10b981; }
        .ans-btn.active-yoq { background: rgba(239, 68, 68, 0.2); border-color: #ef4444; color: #ef4444; }
        .ans-btn.active-na { background: rgba(100, 116, 139, 0.2); border-color: #64748b; color: #cbd5e1; }
        
        .card { background: rgba(30, 41, 59, 0.4); border: 1px solid rgba(51, 65, 85, 0.5); border-radius: 0.75rem; }
        .input-field { background: rgba(15, 23, 42, 0.8); border: 1px solid #334155; color: white; }
        .input-field:focus { border-color: #06b6d4; outline: none; box-shadow: 0 0 0 2px rgba(6, 182, 212, 0.2); }
        
        .nc-badge-minor { background: rgba(16, 185, 129, 0.15); color: #10b981; border-left: 4px solid #10b981; }
        .nc-badge-major { background: rgba(245, 158, 11, 0.15); color: #fbbf24; border-left: 4px solid #f59e0b; }
        .nc-badge-critical { background: rgba(239, 68, 68, 0.15); color: #f87171; border-left: 4px solid #ef4444; }
        
        .modal-backdrop { background: rgba(0, 0, 0, 0.7); backdrop-filter: blur(4px); }
        .modal-content { background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%); border: 1px solid rgba(51, 65, 85, 0.5); animation: modalIn 0.3s ease; }
        @keyframes modalIn { from { opacity: 0; transform: scale(0.95); } to { opacity: 1; transform: scale(1); } }
    </style>
</head>
<body class="min-h-screen text-slate-100">
    <!-- Header -->
    <header class="sticky top-0 z-30 bg-slate-900/95 backdrop-blur-xl border-b border-slate-700/50 px-6 py-3">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-4">
                <a href="index.php" class="text-slate-400 hover:text-white">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                </a>
                <div>
                    <span class="font-mono text-cyan-400 text-sm"><?php echo $audit['audit_code']; ?></span>
                    <h1 class="text-lg font-bold text-white"><?php echo htmlspecialchars($audit['title']); ?></h1>
                </div>
            </div>
            <div class="flex items-center gap-4 text-sm">
                <span id="saveIndicator" class="text-slate-400 flex items-center gap-1"></span>
                <div class="hidden md:flex items-center gap-2">
                    <div class="w-32 h-2 bg-slate-700 rounded-full"><div id="progressBar" class="h-full bg-cyan-500 rounded-full transition-all" style="width: <?php echo $progress; ?>%"></div></div>
                    <span id="progressText" class="text-slate-300 w-10 text-right"><?php echo $progress; ?>%</span>
                </div>
            </div>
        </div>
    </header>

    <div class="flex">
        <!-- Sidebar -->
        <aside class="w-60 fixed h-[calc(100vh-57px)] overflow-y-auto border-r border-slate-700/50 bg-slate-900/50 hidden lg:block p-3">
            <nav class="space-y-1">
                <?php foreach ($sections as $section): ?>
                <a href="?id=<?php echo $auditId; ?>&section=<?php echo $section['id']; ?>" 
                   class="block p-2 rounded-lg text-sm <?php echo $selectedSection == $section['id'] ? 'bg-cyan-500/20 text-white' : 'text-slate-400 hover:bg-slate-800'; ?>">
                    <span class="font-mono mr-1"><?php echo $section['section_number']; ?></span>
                    <?php echo htmlspecialchars($section['section_name']); ?>
                </a>
                <?php endforeach; ?>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="flex-1 lg:ml-60 p-4 lg:p-6">
            <div class="space-y-4">
                <?php if (empty($questions)): ?>
                    <div class="text-center text-slate-500 py-10">Bu bo'limda savollar yo'q</div>
                <?php endif; ?>

                <?php foreach ($questions as $q): 
                    $currentAnswer = $q['answer']; 
                    $hasNC = ($q['nc_id']);
                ?>
                <div class="card p-4" id="q-<?php echo $q['id']; ?>">
                    <div class="flex flex-col md:flex-row md:items-start justify-between gap-3">
                        <div class="flex-1">
                            <p class="text-white text-sm md:text-base mb-1"><?php echo htmlspecialchars($q['question_text']); ?></p>
                            <span class="text-xs text-slate-500">Ball: <?php echo $q['score']; ?></span>
                        </div>
                        <div class="ans-group flex-shrink-0">
                            <button onclick="selectAnswer(<?php echo $q['id']; ?>, 'ha')" class="ans-btn <?php echo $currentAnswer === 'ha' ? 'active-ha' : ''; ?>">HA</button>
                            <button onclick="selectAnswer(<?php echo $q['id']; ?>, 'yoq')" class="ans-btn <?php echo $currentAnswer === 'yoq' ? 'active-yoq' : ''; ?>">YO'Q</button>
                            <button onclick="selectAnswer(<?php echo $q['id']; ?>, 'na')" class="ans-btn <?php echo $currentAnswer === 'na' ? 'active-na' : ''; ?>">TEGISHLI EMAS</button>
                        </div>
                    </div>

                    <div id="extra-<?php echo $q['id']; ?>" class="mt-4 border-t border-slate-700/50 pt-4 <?php echo ($currentAnswer !== 'na') ? '' : 'hidden'; ?>">
                        <div class="grid md:grid-cols-2 gap-4">
                            <div>
                                <label class="text-xs text-slate-400 block mb-1">Izoh</label>
                                <textarea id="comment-<?php echo $q['id']; ?>" class="input-field w-full p-2 rounded-lg text-sm" rows="2"><?php echo htmlspecialchars($q['comment'] ?? ''); ?></textarea>
                            </div>
                            <div>
                                <label class="text-xs text-slate-400 block mb-1">Rasm</label>
                                <input type="file" id="image-<?php echo $q['id']; ?>" class="input-field w-full p-1.5 rounded-lg text-sm text-white" accept="image/*">
                            </div>
                        </div>
                        <button onclick="saveExtra(<?php echo $q['id']; ?>)" class="mt-2 text-xs bg-slate-700 hover:bg-slate-600 px-3 py-1 rounded text-white">Saqlash</button>
                    </div>

                    <!-- NC Badge Container -->
                    <div id="nc-container-<?php echo $q['id']; ?>">
                        <?php if($hasNC): 
                            $sev = $severities[$q['severity_id']] ?? $severities[1];
                            $badgeClass = $q['severity_id'] == 1 ? 'nc-badge-minor' : ($q['severity_id'] == 2 ? 'nc-badge-major' : 'nc-badge-critical');
                        ?>
                        <div class="mt-4 flex flex-col sm:flex-row items-start sm:items-center gap-2 p-3 rounded-lg <?php echo $badgeClass; ?>">
                            <div class="flex items-center gap-2 w-full sm:w-auto">
                                <strong class="text-sm"><?php echo $q['nc_code']; ?></strong>
                                <span class="text-xs px-2 py-0.5 rounded-full bg-black/20 whitespace-nowrap">
                                    <?php echo $sev['name']; ?>
                                </span>
                            </div>
                            <span class="text-xs opacity-80 truncate flex-1 w-full"><?php echo htmlspecialchars($q['nc_description'] ?: 'Tavsif yo\'q'); ?></span>
                            <button onclick="openNCModal(<?php echo $q['id']; ?>, <?php echo $q['nc_id']; ?>, '<?php echo htmlspecialchars($q['nc_description'], ENT_QUOTES); ?>', <?php echo $q['severity_id']; ?>)" class="text-xs text-white hover:underline font-medium whitespace-nowrap mt-2 sm:mt-0">Tahrirlash</button>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </main>
    </div>

    <!-- NC MODAL -->
    <div id="ncModal" class="fixed inset-0 z-50 hidden">
        <div class="modal-backdrop absolute inset-0" onclick="closeNCModal()"></div>
        <div class="relative flex items-center justify-center min-h-screen p-4">
            <div class="modal-content relative w-full max-w-md rounded-2xl p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-bold text-white">Nomuvofiqlikni tasdiqlash</h3>
                    <button onclick="closeNCModal()" class="text-slate-400 hover:text-white text-2xl">&times;</button>
                </div>
                
                <input type="hidden" id="nc_question_id" value="">
                <input type="hidden" id="nc_id" value="">
                
                <div class="space-y-4">
                    <div>
                        <label class="text-slate-300 text-sm block mb-1">Tavsif</label>
                        <textarea id="nc_description" rows="3" class="input-field w-full p-3 rounded-xl text-white" placeholder="Nomuvofiqlik tavsifi..."></textarea>
                    </div>
                    
                    <div>
                        <label class="text-slate-300 text-sm block mb-2">Jiddiylik darajasi</label>
                        <div class="grid grid-cols-3 gap-2">
                            <button type="button" onclick="selectSeverity(1)" class="severity-btn p-3 rounded-xl text-center bg-emerald-500/10 text-emerald-400 border border-transparent hover:border-emerald-500" data-id="1">
                                <div class="w-4 h-4 rounded-full bg-emerald-500 mx-auto mb-1"></div>
                                <span class="text-xs font-medium">Jiddiy bo'lmagan</span>
                            </button>
                            <button type="button" onclick="selectSeverity(2)" class="severity-btn p-3 rounded-xl text-center bg-amber-500/10 text-amber-400 border border-transparent hover:border-amber-500" data-id="2">
                                <div class="w-4 h-4 rounded-full bg-amber-500 mx-auto mb-1"></div>
                                <span class="text-xs font-medium">Jiddiy</span>
                            </button>
                            <button type="button" onclick="selectSeverity(3)" class="severity-btn p-3 rounded-xl text-center bg-red-500/10 text-red-400 border border-transparent hover:border-red-500" data-id="3">
                                <div class="w-4 h-4 rounded-full bg-red-500 mx-auto mb-1"></div>
                                <span class="text-xs font-medium">O'ta jiddiy</span>
                            </button>
                        </div>
                    </div>
                </div>
                
                <div class="flex gap-3 mt-6">
                    <button onclick="closeNCModal()" class="flex-1 py-2 rounded-xl border border-slate-600 text-slate-300">Bekor</button>
                    <button onclick="submitNC()" class="flex-1 py-2 rounded-xl bg-cyan-600 hover:bg-cyan-700 text-white font-semibold">Saqlash</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        let selectedSeverity = 0;
        let currentQuestionId = 0;
        const auditId = <?php echo $auditId; ?>;

        function selectAnswer(qId, answer) {
            const parent = document.getElementById('q-' + qId);
            parent.querySelectorAll('.ans-btn').forEach(b => b.classList.remove('active-ha', 'active-yoq', 'active-na'));
            
            const btnIndex = answer === 'ha' ? 0 : (answer === 'yoq' ? 1 : 2);
            parent.querySelectorAll('.ans-btn')[btnIndex].classList.add('active-' + answer);

            document.getElementById('extra-' + qId).classList.remove('hidden');

            if (answer === 'yoq') {
                autoCreateNC(qId);
            } else {
                // Boshqa javoblar uchun oddiy saqlash (ixtiyoriy)
                // saveAnswerData(qId, answer, false); 
            }
        }

        function autoCreateNC(qId) {
            const formData = new FormData();
            formData.append('action', 'auto_create_nc');
            formData.append('question_id', qId);

            fetch('conduct_audit.php?id=' + auditId, { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    updateProgress(); 
                    // 1. Badge ni chiqaramiz
                    renderNCBadge(qId, data.nc_code, data.nc_id, 1, data.description); 
                    showIndicator('Yaratildi: ' + data.description);
                    
                    // 2. Modalni avtomatik ochamiz (Tahrirlash uchun)
                    openNCModal(qId, data.nc_id, data.description, 1);
                } else {
                    showIndicator(data.message || "Mavjud");
                    // Agar allaqachon mavjud bo'lsa va ID qaytgan bo'lsa, shuni ochamiz
                    if(data.nc_id) {
                        renderNCBadge(qId, data.nc_code, data.nc_id, 1, "Mavjud nomuvofiqlik");
                    }
                }
            })
            .catch(err => {
                console.error(err);
                alert("Server xatosi!");
            });
        }

        function renderNCBadge(qId, code, ncId, severityId, desc) {
            const container = document.getElementById('nc-container-' + qId);
            
            let badgeClass = 'nc-badge-minor';
            let sevName = 'Jiddiy bo\'lmagan';
            let colorClass = 'text-emerald-400';

            if(severityId == 2) { badgeClass = 'nc-badge-major'; sevName = 'Jiddiy'; colorClass = 'text-amber-400'; }
            if(severityId == 3) { badgeClass = 'nc-badge-critical'; sevName = 'O\'ta jiddiy'; colorClass = 'text-red-400'; }

            // Tavsifni tozalash (quote issues uchun)
            const safeDesc = desc.replace(/'/g, "\\'");

            const html = `
                <div class="mt-4 flex flex-col sm:flex-row items-start sm:items-center gap-2 p-3 rounded-lg ${badgeClass}">
                    <div class="flex items-center gap-2 w-full sm:w-auto">
                        <strong class="text-sm">${code}</strong>
                        <span class="text-xs px-2 py-0.5 rounded-full bg-black/20 whitespace-nowrap ${colorClass}">
                            ${sevName}
                        </span>
                    </div>
                    <span class="text-xs opacity-80 truncate flex-1 w-full">${desc ? desc : 'Tavsif yo\'q'}</span>
                    <button onclick="openNCModal(${qId}, ${ncId}, '${safeDesc}', ${severityId})" class="text-xs text-white hover:underline font-medium whitespace-nowrap mt-2 sm:mt-0">Tahrirlash</button>
                </div>
            `;
            
            container.innerHTML = html;
        }

        function saveExtra(qId) {
            const parent = document.getElementById('q-' + qId);
            let answer = 'na';
            if(parent.querySelector('.active-ha')) answer = 'ha';
            if(parent.querySelector('.active-yoq')) answer = 'yoq';
            
            const formData = new FormData();
            formData.append('action', 'save_answer'); 
            formData.append('audit_id', auditId);
            formData.append('question_id', qId);
            formData.append('answer', answer);
            formData.append('comment', document.getElementById('comment-' + qId).value);
            
            const imageInput = document.getElementById('image-' + qId);
            if (imageInput.files[0]) {
                formData.append('image', imageInput.files[0]);
            }

            fetch('../api.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    showIndicator('Saqlandi');
                }
            });
        }

        function openNCModal(qId, ncId, desc, sevId) {
            currentQuestionId = qId;
            document.getElementById('nc_question_id').value = qId;
            document.getElementById('nc_id').value = ncId || '';
            document.getElementById('nc_description').value = desc;
            
            document.querySelectorAll('.severity-btn').forEach(b => b.classList.remove('border-emerald-500', 'border-amber-500', 'border-red-500'));
            if (sevId > 0) selectSeverity(sevId);
            
            document.getElementById('ncModal').classList.remove('hidden');
        }

        function closeNCModal() { document.getElementById('ncModal').classList.add('hidden'); }

        function selectSeverity(id) {
            selectedSeverity = id;
            const colors = {1: 'emerald', 2: 'amber', 3: 'red'};
            document.querySelectorAll('.severity-btn').forEach(b => {
                const bid = b.dataset.id;
                b.classList.remove('border-emerald-500', 'border-amber-500', 'border-red-500');
                if (bid == id) b.classList.add('border-' + colors[id] + '-500');
            });
        }

        function submitNC() {
            if (!selectedSeverity) return alert("Jiddiylik darajasini tanlang!");
            
            const formData = new FormData();
            formData.append('action', 'save_nc');
            formData.append('audit_id', auditId);
            formData.append('question_id', currentQuestionId);
            formData.append('severity_id', selectedSeverity);
            formData.append('description', document.getElementById('nc_description').value);
            if (document.getElementById('nc_id').value) {
                formData.append('nc_id', document.getElementById('nc_id').value);
            }

            fetch('../api.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    closeNCModal();
                    location.reload(); // O'zgarishlarni ko'rish uchun sahifani yangilash
                } else {
                    alert(data.message);
                }
            });
        }

        function updateProgress() {
            const bar = document.getElementById('progressBar');
            let currentW = parseInt(bar.style.width);
            if(currentW < 100) {
                bar.style.width = (currentW + 1) + '%';
                document.getElementById('progressText').innerText = (currentW + 1) + '%';
            }
        }
        
        function showIndicator(msg) {
            const el = document.getElementById('saveIndicator');
            el.innerHTML = `<span class="text-emerald-400">${msg}</span>`;
            setTimeout(() => el.innerHTML = '', 2000);
        }
    </script>
</body>
</html>