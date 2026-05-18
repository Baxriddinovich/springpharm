import re
from pathlib import Path
root = Path(r'd:\OSPanel\domains\spring')
files = {
    'dashboard.php': 'dashboard',
    'audits.php': 'audits',
    'reports.php': 'reports',
    'sections.php': 'sections',
    'checklists.php': 'checklists',
    'users.php': 'users',
    'logs.php': 'logs',
    'non_conformities.php': 'non_conformities',
}
pattern = re.compile(r'(?s)<div class="lg:hidden fixed top-0 left-0 right-0 z-40 bg-slate-900/95 backdrop-blur-xl border-b border-slate-700/50 px-4 py-3 flex items-center justify-between">.*?</aside>')
for name, active in files.items():
    path = root / name
    if not path.exists():
        print(f"Missing {name}")
        continue
    text = path.read_text(encoding='utf-8')
    replacement = '''<div class="lg:hidden fixed top-0 left-0 right-0 z-40 bg-slate-900/95 backdrop-blur-xl border-b border-slate-700/50 px-4 py-3 flex items-center justify-between">
<?php $activePage='%s'; include 'inc/sidebar.php'; ?>
''' % active
    new = pattern.sub(replacement, text, count=1)
    if new != text:
        path.write_text(new, encoding='utf-8')
        print(f"Updated {name}")
    else:
        print(f"No sidebar block found in {name}")
