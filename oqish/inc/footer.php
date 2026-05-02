    <!-- TOAST CONTAINER -->
    <div id="toastContainer" class="fixed bottom-5 right-5 z-[100] flex flex-col gap-3"></div>

    <!-- CONFIRM MODAL -->
    <div id="confirmModal" class="fixed inset-0 z-[55] hidden">
        <div class="overlay-bg absolute inset-0" onclick="closeConfirm()"></div>
        <div class="relative z-10 flex items-center justify-center min-h-screen p-4">
            <div class="glass-card rounded-2xl p-6 max-w-sm w-full text-center fade-in">
                <div class="w-14 h-14 rounded-2xl bg-cyan-500/10 border border-cyan-500/20 flex items-center justify-center mx-auto mb-4">
                    <svg class="w-7 h-7 text-cyan-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <h3 id="confirmTitle" class="text-lg font-semibold text-white mb-2"></h3>
                <p id="confirmText" class="text-sm text-slate-400 mb-6"></p>
                <div class="flex items-center gap-3 justify-center">
                    <button onclick="closeConfirm()" class="px-5 py-2.5 rounded-xl text-sm font-medium text-slate-300 bg-slate-800 hover:bg-slate-700 transition">Bekor qilish</button>
                    <button id="confirmBtn" class="btn-primary px-5 py-2.5 rounded-xl text-sm font-semibold text-white">Tasdiqlash</button>
                </div>
            </div>
        </div>
    </div>

    <script src="assets/js/dashboard.js"></script>
</body>
</html>
