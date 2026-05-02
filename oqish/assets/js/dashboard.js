// ═══════════════════════════════════════════════════════
// SIDEBAR
// ═══════════════════════════════════════════════════════
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    sidebar.classList.toggle('open');
    overlay.classList.toggle('hidden');
}

// ═══════════════════════════════════════════════════════
// TOAST
// ═══════════════════════════════════════════════════════
function showToast(message, type = 'info') {
    const container = document.getElementById('toastContainer');
    const colors = {
        success: 'border-emerald-500/30 bg-emerald-500/10',
        error: 'border-red-500/30 bg-red-500/10',
        info: 'border-cyan-500/30 bg-cyan-500/10',
        warning: 'border-amber-500/30 bg-amber-500/10'
    };
    const icons = {
        success: '<svg class="w-4 h-4 text-emerald-400" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>',
        error: '<svg class="w-4 h-4 text-red-400" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/></svg>',
        info: '<svg class="w-4 h-4 text-cyan-400" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/></svg>',
        warning: '<svg class="w-4 h-4 text-amber-400" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>'
    };
    const textColors = {
        success: 'text-emerald-300',
        error: 'text-red-300',
        info: 'text-cyan-300',
        warning: 'text-amber-300'
    };

    const toast = document.createElement('div');
    toast.className = 'toast flex items-center gap-3 px-4 py-3 rounded-xl border ' + colors[type] + ' backdrop-blur-lg max-w-sm';
    toast.innerHTML = icons[type] + '<span class="text-sm ' + textColors[type] + '">' + message + '</span>';
    container.appendChild(toast);

    setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transform = 'translateX(100%)';
        toast.style.transition = 'all 0.3s ease';
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

// ═══════════════════════════════════════════════════════
// CONFIRM MODAL
// ═══════════════════════════════════════════════════════
let confirmCallback = null;
function showConfirm(title, text, onConfirm) {
    document.getElementById('confirmTitle').textContent = title;
    document.getElementById('confirmText').textContent = text;
    document.getElementById('confirmModal').classList.remove('hidden');
    confirmCallback = onConfirm;
    document.getElementById('confirmBtn').onclick = function () {
        closeConfirm();
        if (confirmCallback) confirmCallback();
    };
}
function closeConfirm() {
    document.getElementById('confirmModal').classList.add('hidden');
    confirmCallback = null;
}

// ═══════════════════════════════════════════════════════
// MATERIAL KO'RILDI (AJAX)
// ═══════════════════════════════════════════════════════
async function markAsViewed(materialId, moduleId) {
    try {
        const formData = new FormData();
        formData.append('action', 'mark_viewed');
        formData.append('material_id', materialId);
        formData.append('module_id', moduleId);

        const res = await fetch(window.location.href, { method: 'POST', body: formData });
        const data = await res.json();

        if (data.success) {
            updateMaterialProgress(data.viewed, data.total);
            
            // Mark the specific card as viewed in UI
            const cards = document.querySelectorAll('.material-card');
            cards.forEach(card => {
                if (card.getAttribute('onclick') && card.getAttribute('onclick').includes(materialId)) {
                    card.classList.remove('border-slate-700/50');
                    card.classList.add('border-emerald-500/30', 'bg-emerald-500/5');
                    const statusText = card.querySelector('.text-slate-500');
                    if (statusText && statusText.textContent === "O'qilmagan") {
                        statusText.textContent = "Ko'rildi";
                        statusText.classList.remove('text-slate-500');
                        statusText.classList.add('text-emerald-400');
                    }
                }
            });

            if (data.all_viewed) {
                showToast('Barcha materiallar ko\'rildi! "Tugatish" tugmasini bosing.', 'success');
                // Optional: Show the complete button if it was hidden
                const completeBtn = document.querySelector('button[onclick*="completeMaterials"]');
                if (completeBtn) completeBtn.classList.remove('hidden');
            }
        }
    } catch (err) { console.error(err); }
}

async function completeMaterials(moduleId) {
    try {
        const formData = new FormData();
        formData.append('action', 'complete_materials');
        formData.append('module_id', moduleId);

        const res = await fetch(window.location.href, { method: 'POST', body: formData });
        const data = await res.json();

        if (data.success && data.redirect) {
            window.location.href = data.redirect;
        }
    } catch (err) { console.error(err); }
}

function updateMaterialProgress(viewed, total) {
    document.querySelectorAll('.progress-bar').forEach(bar => {
        const fill = bar.querySelector('.progress-fill');
        if (fill && total > 0) fill.style.width = (viewed / total * 100) + '%';
    });
}

// ═══════════════════════════════════════════════════════
// TEST LOGIC
// ═══════════════════════════════════════════════════════
function selectOption(radio) {
    const questionId = radio.name.match(/\d+/)[0];
    document.querySelectorAll('.radio-option[data-question="' + questionId + '"]').forEach(opt => opt.classList.remove('selected'));
    radio.closest('.radio-option').classList.add('selected');
    updateAnswerCount();
}

function updateAnswerCount() {
    const total = document.querySelectorAll('input[type="radio"]:checked').length;
    const qCount = new Set(Array.from(document.querySelectorAll('input[type="radio"]')).map(i => i.name)).size;
    const el = document.getElementById('answerCount');
    if (el) el.textContent = total + ' / ' + qCount + ' savolga javob berildi';
}

async function submitTest(e, moduleId) {
    e.preventDefault();
    const qCount = new Set(Array.from(document.querySelectorAll('input[type="radio"]')).map(i => i.name)).size;
    const answered = document.querySelectorAll('input[type="radio"]:checked').length;

    if (answered < qCount) {
        showConfirm('Testni yakunlash', answered + ' ta savolga javob berildi. Davom etasizmi?', () => doSubmitTest(moduleId));
    } else {
        doSubmitTest(moduleId);
    }
}

async function doSubmitTest(moduleId) {
    const btn = document.getElementById('submitTestBtn');
    btn.disabled = true;
    try {
        const formData = new FormData();
        formData.append('action', 'submit_test');
        formData.append('module_id', moduleId);
        document.querySelectorAll('input[type="radio"]:checked').forEach(r => {
            formData.append('answers[' + r.name.match(/\d+/)[0] + ']', r.value);
        });
        const res = await fetch(window.location.href, { method: 'POST', body: formData });
        const data = await res.json();
        if (data.success && data.redirect) window.location.href = data.redirect;
    } catch (err) { showToast('Xatolik yuz berdi', 'error'); btn.disabled = false; }
}

function retakeTest(moduleId) {
    showConfirm('Qayta topshirish', 'Oldingi natija o\'chiriladi. Davom etasizmi?', () => {
        window.location.href = '?page=test&id=' + moduleId + '&retake=1';
    });
}

// ═══════════════════════════════════════════════════════
// ON-PAGE VIEWER
// ═══════════════════════════════════════════════════════
function openMaterial(url, name, type, id, moduleId) {
    const viewer = document.getElementById('materialViewer');
    const frame = document.getElementById('viewerFrame');
    const video = document.getElementById('viewerVideo');
    const img = document.getElementById('viewerImage');
    const loading = document.getElementById('viewerLoading');
    const fileName = document.getElementById('viewerFileName');

    viewer.classList.remove('hidden');
    viewer.scrollIntoView({ behavior: 'smooth', block: 'start' });
    loading.classList.remove('hidden');
    fileName.textContent = name;

    frame.classList.add('hidden');
    video.classList.add('hidden');
    img.classList.add('hidden');

    if (type.includes('video')) {
        video.src = url;
        video.classList.remove('hidden');
        loading.classList.add('hidden');
    } else if (type.includes('image')) {
        img.src = url;
        img.classList.remove('hidden');
        loading.classList.add('hidden');
    } else {
        frame.src = url + (url.includes('.pdf') ? '#toolbar=0' : '');
        frame.classList.remove('hidden');
        frame.onload = () => loading.classList.add('hidden');
    }

    markAsViewed(id, moduleId);
}

function closeViewer() {
    const viewer = document.getElementById('materialViewer');
    const video = document.getElementById('viewerVideo');
    const frame = document.getElementById('viewerFrame');
    
    viewer.classList.add('hidden');
    video.pause();
    video.src = "";
    frame.src = "";
}

// ═══════════════════════════════════════════════════════
// SECURITY
// ═══════════════════════════════════════════════════════
document.addEventListener('contextmenu', e => {
    if (e.target.closest('.no-copy')) { e.preventDefault(); showToast('Nusxalash taqiqlangan', 'warning'); }
});
document.addEventListener('keydown', e => {
    if ((e.ctrlKey || e.metaKey) && (e.key === 'p' || e.key === 's')) { e.preventDefault(); showToast('Taqiqlangan amal', 'warning'); }
});
