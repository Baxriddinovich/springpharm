<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
require_once '../db.php';
requireLogin();
 $user = getCurrentUser();

// --- BACKEND LOGIC ---

// 1. Lavozimlar ro'yxati
 $positions = $pdo->query("SELECT * FROM positions ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

// 2. Faol modullar ro'yxati
 $modules = $pdo->query("SELECT * FROM training_modules WHERE status = 'active' ORDER BY title ASC")->fetchAll(PDO::FETCH_ASSOC);

// 3. Bog'langan matritsani olish (array formatda: pos_id => [mod_id, mod_id...])
 $matrixData = [];
 $stmt = $pdo->query("SELECT position_id, module_id FROM training_matrix");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $matrixData[$row['position_id']][] = $row['module_id'];
}

// JS uchun ma'lumotlarni JSON ga aylantiramiz
 $jsonPositions = json_encode($positions);
 $jsonModules = json_encode($modules);
 $jsonMatrix = json_encode($matrixData);
?>
<!DOCTYPE html>
<html lang="uz">

<head>
    <meta charset="UTF-8">
    <title>Matritsa - GMP</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* Dashboard styles */
        :root {
            --bg-primary: #0a0f1a;
            --accent-cyan: #06b6d4;
            --glass-bg: rgba(26, 35, 50, 0.7);
            --glass-border: rgba(51, 65, 85, 0.5);
        }

        * {
            font-family: 'Inter', sans-serif;
            scrollbar-width: thin;
            scrollbar-color: #475569 #0f172a;
        }

        /* Webkit scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }
        ::-webkit-scrollbar-track {
            background: #0f172a; 
        }
        ::-webkit-scrollbar-thumb {
            background: #475569; 
            border-radius: 4px;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: #64748b; 
        }

        body {
            background: var(--bg-primary);
            color: #f1f5f9;
        }

        .glass-card {
            background: var(--glass-bg);
            backdrop-filter: blur(12px);
            border: 1px solid var(--glass-border);
        }

        .nav-item {
            transition: all 0.2s;
            border-left: 3px solid transparent;
        }

        .nav-item:hover,
        .nav-item.active {
            background: rgba(6, 182, 212, 0.1);
            border-left-color: var(--accent-cyan);
            color: #fff;
        }

        /* Custom Checkbox Card Style */
        .module-card {
            transition: all 0.2s;
            cursor: pointer;
            border: 1px solid rgba(51, 65, 85, 0.5);
        }
        
        .module-card:hover {
            border-color: rgba(6, 182, 212, 0.4);
            background: rgba(30, 41, 59, 0.8);
        }

        .module-card.selected {
            border-color: #06b6d4;
            background: rgba(6, 182, 212, 0.1);
            box-shadow: 0 0 15px rgba(6, 182, 212, 0.1);
        }

        /* Position List Item Active State */
        .position-item.active {
            background: linear-gradient(90deg, rgba(6, 182, 212, 0.15) 0%, transparent 100%);
            border-left: 4px solid #06b6d4;
            color: #fff;
        }
    </style>
</head>

<body class="min-h-screen flex text-slate-100 relative">
    <!-- SIDEBAR -->
    <?php include 'sidebar.php'; ?>

    <!-- Mobil fon (Backdrop) -->
    <div id="sidebarBackdrop" onclick="toggleSidebar()" class="fixed inset-0 bg-black/80 z-40 hidden lg:hidden backdrop-blur-sm transition-opacity"></div>

    <!-- Main Content -->
    <main class="flex-1 min-h-screen w-full">
        <!-- Header -->
        <header class="sticky top-0 z-40 bg-slate-900/90 backdrop-blur-md border-b border-slate-800 px-8 py-4 justify-between items-center">
            <div>
                <h2 class="text-xl font-semibold text-white">Trening Matritsasi</h2>
                <p class="text-sm text-slate-500">Lavozimga qarab majburiy mashg'ulotlarni sozlash</p>
            </div>
        </header>

        <div class="flex flex-col lg:flex-row h-[calc(100vh-73px)] overflow-hidden">
            
            <!-- Chap panel: Lavozimlar ro'yxati (30% kenglik) -->
            <div class="w-full lg:w-1/3 bg-slate-900/50 border-r border-slate-800 flex flex-col h-full">
                <!-- Qidiruv -->
                <div class="p-4 border-b border-slate-800 bg-slate-900">
                    <label class="text-xs text-slate-500 mb-1 block">Lavozimni qidiring</label>
                    <div class="relative">
                        <input type="text" id="posSearch" placeholder="Nomini yozing..." class="w-full bg-slate-800 border border-slate-700 text-sm text-white rounded-lg px-4 py-2 pl-9 focus:outline-none focus:border-cyan-500 transition">
                        <svg class="w-4 h-4 text-slate-400 absolute left-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                    </div>
                </div>
                <!-- Ro'yxat -->
                <div id="positionList" class="flex-1 overflow-y-auto p-2 space-y-1">
                    <!-- JS tomonidan to'ldiriladi -->
                </div>
            </div>

            <!-- O'ng panel: Tanlangan lavozim uchun modullar (70% kenglik) -->
            <div class="w-full lg:w-2/3 flex flex-col h-full bg-slate-900/30">
                <!-- O'ng panel boshi (Sarlavha va Qidiruv) -->
                <div class="p-6 border-b border-slate-800 bg-slate-900/50 backdrop-blur-sm z-10">
                    <div class="flex justify-between items-end mb-4">
                        <div>
                            <h3 id="selectedPosName" class="text-2xl font-bold text-white">Lavozimni tanlang</h3>
                            <p id="selectedPosCount" class="text-sm text-cyan-400 mt-1">0 / 0 modul belgilangan</p>
                        </div>
                        <div id="saveStatus" class="text-emerald-400 text-sm font-medium opacity-0 transition-opacity duration-500">
                            <span class="flex items-center gap-1">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                Saqlandi
                            </span>
                        </div>
                    </div>

                    <!-- Modul qidiruvi -->
                    <div class="relative max-w-md">
                        <input type="text" id="modSearch" placeholder="Modul kodi yoki nomi bo'yicha qidirish..." class="w-full bg-slate-800 border border-slate-700 text-sm text-white rounded-lg px-4 py-2 pl-9 focus:outline-none focus:border-cyan-500 transition">
                        <svg class="w-4 h-4 text-slate-400 absolute left-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                    </div>
                </div>

                <!-- Modullar Grid (Scrollable) -->
                <div id="modulesGrid" class="flex-1 overflow-y-auto p-6">
                    <div class="text-center text-slate-500 mt-20">
                        <svg class="w-16 h-16 mx-auto mb-4 opacity-20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path></svg>
                        <p>Modullarni ko'rish uchun chap tomondan lavozimni tanlang.</p>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script>
        const positions = <?php echo $jsonPositions; ?>;
        const modules = <?php echo $jsonModules; ?>;
        let matrix = <?php echo $jsonMatrix; ?>;

        // State
        let currentPosId = null;

        // DOM Elements
        const positionListEl = document.getElementById('positionList');
        const modulesGridEl = document.getElementById('modulesGrid');
        const selectedPosNameEl = document.getElementById('selectedPosName');
        const selectedPosCountEl = document.getElementById('selectedPosCount');
        const posSearchInput = document.getElementById('posSearch');
        const modSearchInput = document.getElementById('modSearch');
        const saveStatusEl = document.getElementById('saveStatus');

        // --- INIT ---
        function init() {
            renderPositionList(positions);
            // Agar lavozimlar bo'lsa, birinchisini tanlash shart emas, lekin bo'lishi mumkin
            if(positions.length > 0) {
               // selectPosition(positions[0].id); // Agar avtomatik ochilishi kerak bo'lsa, kommentni oling
            }
        }

        // --- RENDER FUNCTIONS ---

        // 1. Lavozimlar ro'yxatini chiqarish
        function renderPositionList(data) {
            positionListEl.innerHTML = '';
            
            if (data.length === 0) {
                positionListEl.innerHTML = '<div class="p-4 text-center text-slate-500 text-sm">Topilmadi</div>';
                return;
            }

            data.forEach(pos => {
                const assignedCount = matrix[pos.id] ? matrix[pos.id].length : 0;
                const totalCount = modules.length;
                
                const item = document.createElement('div');
                item.className = `position-item p-3 rounded-lg cursor-pointer hover:bg-slate-800 transition flex justify-between items-center group ${currentPosId === pos.id ? 'active' : ''}`;
                item.onclick = () => selectPosition(pos.id);

                item.innerHTML = `
                    <div class="overflow-hidden">
                        <div class="font-medium text-sm text-slate-200 group-hover:text-white truncate">${pos.name}</div>
                    </div>
                    <div class="text-xs font-mono bg-slate-800 px-2 py-1 rounded text-slate-400 group-hover:text-cyan-400">
                        ${assignedCount} / ${totalCount}
                    </div>
                `;
                positionListEl.appendChild(item);
            });
        }

        // 2. Lavozimni tanlash
        function selectPosition(id) {
            currentPosId = id;
            
            // Chap tomondagi aktiv klassni yangilash
            // Soddalik uchun butun ro'yxatni qayta render qilamiz (katta ma'lumot bo'lsa optimallashtirish kerak)
            renderPositionList(
                positions.filter(p => p.name.toLowerCase().includes(posSearchInput.value.toLowerCase()))
            );

            // O'ng tomon sarlavhasini yangilash
            const pos = positions.find(p => p.id == id);
            if(pos) {
                selectedPosNameEl.textContent = pos.name;
                renderModuleGrid(id);
            }
        }

        // 3. Modullar to'rini (grid) chiqarish
        function renderModuleGrid(posId) {
            const currentMods = matrix[posId] || [];
            const searchTerm = modSearchInput.value.toLowerCase();
            
            modulesGridEl.innerHTML = '';

            // Filtr
            const filteredMods = modules.filter(m => 
                m.title.toLowerCase().includes(searchTerm) || 
                m.code.toLowerCase().includes(searchTerm)
            );

            if (filteredMods.length === 0) {
                modulesGridEl.innerHTML = '<div class="text-center text-slate-500 mt-10">Mos modullar topilmadi</div>';
                selectedPosCountEl.textContent = `0 / ${modules.length} modul belgilangan`;
                return;
            }

            const gridContainer = document.createElement('div');
            gridContainer.className = 'grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-3';

            filteredMods.forEach(mod => {
                const isChecked = currentMods.includes(mod.id);
                
                const card = document.createElement('div');
                card.className = `module-card bg-slate-800/50 p-4 rounded-xl flex items-start gap-3 ${isChecked ? 'selected' : ''}`;
                
                // Click event: Toggle status
                card.onclick = () => toggleModule(posId, mod.id, !isChecked);

                card.innerHTML = `
                    <div class="mt-1">
                        <div class="w-5 h-5 rounded border ${isChecked ? 'bg-cyan-500 border-cyan-500' : 'border-slate-500 bg-slate-900'} flex items-center justify-center transition-colors">
                            ${isChecked ? '<svg class="w-3 h-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>' : ''}
                        </div>
                    </div>
                    <div class="flex-1">
                        <div class="flex justify-between items-start">
                            <span class="text-xs font-bold text-cyan-400 bg-cyan-950/50 px-1.5 py-0.5 rounded border border-cyan-900/50">${mod.code}</span>
                        </div>
                        <h4 class="text-sm font-medium text-slate-200 mt-1 leading-snug">${mod.title}</h4>
                    </div>
                `;
                gridContainer.appendChild(card);
            });

            modulesGridEl.appendChild(gridContainer);
            selectedPosCountEl.textContent = `${currentMods.length} / ${modules.length} modul belgilangan`;
        }

        // --- ACTIONS ---

        // Modul statusini o'zgartirish (Checkbox)
        function toggleModule(posId, modId, newStatus) {
            // UI ni darhol yangilab qo'yymiz (Fast UX)
            if (!matrix[posId]) matrix[posId] = [];
            
            if (newStatus) {
                if (!matrix[posId].includes(modId)) matrix[posId].push(modId);
            } else {
                matrix[posId] = matrix[posId].filter(id => id !== modId);
            }

            // Re-render right side
            renderModuleGrid(posId);
            // Re-render left side to update counters
            renderPositionList(
                positions.filter(p => p.name.toLowerCase().includes(posSearchInput.value.toLowerCase()))
            );

            // Serverga yuborish
            const isChecked = newStatus ? 1 : 0;
            
            fetch('matrix_update.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `pos_id=${posId}&mod_id=${modId}&status=${isChecked}`
            })
            .then(response => response.text())
            .then(text => {
                try {
                    const data = JSON.parse(text);
                    if (!data.success) throw new Error(data.error || 'Xatolik');
                    showSaveStatus();
                } catch (e) {
                    console.error(e);
                    alert('Xatolik yuz berdi. Internetni tekshiring.');
                    // Xatolik bo'lsa, UI ni qaytarib eski holatga qaytarish kerak (bu yerda soddalashtirildi)
                }
            })
            .catch(err => {
                console.error(err);
                alert('Serverga ulanib bo\'lmadi');
            });
        }

        function showSaveStatus() {
            saveStatusEl.classList.remove('opacity-0');
            setTimeout(() => {
                saveStatusEl.classList.add('opacity-0');
            }, 2000);
        }

        // --- SEARCH LISTENERS ---

        posSearchInput.addEventListener('input', (e) => {
            const term = e.target.value.toLowerCase();
            const filtered = positions.filter(p => p.name.toLowerCase().includes(term));
            renderPositionList(filtered);
        });

        modSearchInput.addEventListener('input', () => {
            if (currentPosId) {
                renderModuleGrid(currentPosId);
            }
        });

        // Start
        init();

        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const backdrop = document.getElementById('sidebarBackdrop');
            if (sidebar.classList.contains('-translate-x-full')) {
                sidebar.classList.remove('-translate-x-full');
                sidebar.classList.add('translate-x-0');
                backdrop.classList.remove('hidden');
            } else {
                sidebar.classList.add('-translate-x-full');
                sidebar.classList.remove('translate-x-0');
                backdrop.classList.add('hidden');
            }
        }

    </script>
</body>
</html>