<!doctype html>
<?php $currentRoute = (string) ($_GET['route'] ?? 'dashboard'); ?>
<?php $hasSidebar = Auth::check(); ?>
<?php
$scriptName = (string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php');
$assetBasePath = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');
if ($assetBasePath === '.' || $assetBasePath === '') {
    $assetBasePath = '';
}

// Assets live under /public/assets; in some setups SCRIPT_NAME does not include /public.
if ($assetBasePath === '' || !preg_match('#/public$#', $assetBasePath)) {
    $assetBasePath = rtrim($assetBasePath . '/public', '/');
}
$loginBackgroundCandidates = [
    '/assets/images/login-bg-dental.webp',
    '/assets/images/login-bg-dental.jpg',
    '/assets/images/login-bg-dental.jpeg',
    '/assets/images/login-bg-dental.png',
    '/assets/images/login-bg.webp',
    '/assets/images/login-bg.jpg',
    '/assets/images/login-bg.jpeg',
    '/assets/images/login-bg.png',
];
$loginBackgroundUrl = $assetBasePath . $loginBackgroundCandidates[0];
foreach ($loginBackgroundCandidates as $candidatePath) {
    $candidateFile = __DIR__ . '/../../../public' . str_replace('/', DIRECTORY_SEPARATOR, $candidatePath);
    if (is_file($candidateFile)) {
        $loginBackgroundUrl = $assetBasePath . $candidatePath;
        break;
    }
}
$isLoginBackgroundPage = !$hasSidebar && in_array($currentRoute, ['', 'login'], true);
?>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Atendy</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap');
        :root{
            --bg:#dbe2ea;
            --surface:#e9edf3;
            --surface-soft:#f1f4f8;
            --text:#0f172a;
            --muted:#4b5563;
            --line:#c8d1dc;
            --primary:#1e3a8a;
            --primary-600:#1e40af;
            --primary-soft:#dbe7ff;
            --danger:#dc2626;
            --radius:14px;
            --shadow:0 10px 30px rgba(15,23,42,.08);
            --sidebar-w:250px;
            --sidebar-collapsed-w:84px;
        }
        *{box-sizing:border-box}
        html,body{height:100%}
        body{margin:0;background:var(--bg);color:var(--text);font-family:'Plus Jakarta Sans',Inter,Segoe UI,Roboto,Arial,sans-serif}

        .auth-top{padding:20px 20px 0}
        .auth-brand{display:inline-flex;align-items:center;gap:10px;text-decoration:none;color:#1e3a8a;font-weight:700}
        .brand-badge{display:inline-flex;align-items:center;justify-content:center;width:32px;height:32px;border-radius:10px;background:linear-gradient(135deg,#2563eb,#60a5fa);color:#fff}

        body.login-screen{
            background:
                linear-gradient(118deg, rgba(14,23,38,.56) 0%, rgba(14,23,38,.35) 46%, rgba(14,23,38,.2) 100%),
                url('<?= e($loginBackgroundUrl) ?>') center center / cover no-repeat fixed;
        }
        body.login-screen .auth-top,
        body.login-screen .auth-login-shell{position:relative;z-index:1}
        .auth-login-shell{min-height:calc(100vh - 74px);display:flex;align-items:center;justify-content:center;padding:26px 16px 30px}
        .login-card{
            width:min(100%,460px);
            margin:0;
            background:linear-gradient(180deg,rgba(243,247,251,.92),rgba(230,237,246,.9));
            border:1px solid rgba(210,220,232,.95);
            box-shadow:0 18px 38px rgba(9,20,36,.24);
            backdrop-filter:blur(7px);
        }

        .app-shell{min-height:100vh;display:flex;transition:.2s ease}
        .sidebar{width:var(--sidebar-w);background:linear-gradient(180deg,#0f172a 0%,#132036 100%);color:#cbd5e1;padding:18px 14px;position:sticky;top:0;height:100vh;border-right:1px solid rgba(255,255,255,.08);transition:width .22s ease,padding .22s ease}
        .sidebar-backdrop{display:none}
        .sidebar-brand{display:flex;align-items:center;gap:10px;padding:8px 10px 14px;border-bottom:1px solid rgba(255,255,255,.08);margin-bottom:14px;white-space:nowrap;overflow:hidden}
        .sidebar-brand strong{font-size:18px;color:#fff}
        .sidebar-nav{display:flex;flex-direction:column;gap:6px}
        .sidebar-nav a{display:flex;align-items:center;gap:10px;color:#cbd5e1;text-decoration:none;padding:10px 12px;border-radius:11px;font-size:14px;font-weight:500;transition:.18s ease;white-space:nowrap;overflow:hidden;position:relative}
        .sidebar-nav a .nav-icon{font-size:16px;line-height:1;min-width:20px;text-align:center}
        .sidebar-nav a .nav-label{transition:opacity .16s ease, transform .16s ease}
        .sidebar-nav a:hover{background:rgba(148,163,184,.16);color:#fff}
        .sidebar-nav a.active{background:linear-gradient(90deg,rgba(37,99,235,.35),rgba(37,99,235,.18));color:#fff;border:1px solid rgba(147,197,253,.35)}
        .sidebar-footer{position:absolute;bottom:16px;left:14px;right:14px;font-size:12px;color:#93a4bf}

        .app-shell.sidebar-collapsed .sidebar{width:var(--sidebar-collapsed-w);padding-left:10px;padding-right:10px}
        .app-shell.sidebar-collapsed .sidebar-brand strong,
        .app-shell.sidebar-collapsed .sidebar-footer,
        .app-shell.sidebar-collapsed .sidebar-nav a .nav-label{opacity:0;transform:translateX(-6px);pointer-events:none}
        .app-shell.sidebar-collapsed .sidebar-brand{justify-content:center}
        .app-shell.sidebar-collapsed .sidebar-nav a{justify-content:center;padding-left:8px;padding-right:8px}
        .app-shell.sidebar-collapsed .sidebar-nav a:hover::after{
            content:attr(data-label);
            position:absolute;
            left:64px;
            background:#0f172a;
            color:#fff;
            border:1px solid rgba(148,163,184,.35);
            border-radius:8px;
            padding:6px 9px;
            font-size:12px;
            z-index:30;
            box-shadow:0 8px 20px rgba(0,0,0,.24);
        }

        .main-area{flex:1;min-width:0}
        .top-header{height:68px;display:flex;align-items:center;justify-content:space-between;padding:0 22px;background:rgba(222,229,238,.88);backdrop-filter:blur(8px);border-bottom:1px solid var(--line);position:sticky;top:0;z-index:10}
        .top-header .title{font-size:14px;color:var(--muted)}
        .top-header-left{display:flex;align-items:center;gap:10px}
        .sidebar-toggle{height:34px;width:34px;border-radius:9px;border:1px solid #c7d2e0;background:#eef2f7;color:#1f2937;display:inline-flex;align-items:center;justify-content:center;cursor:pointer;font-size:15px}
        .sidebar-toggle:hover{background:#e2e8f0}
        .container{max-width:1240px;margin:24px auto;padding:0 20px}

        .page-title{margin:0 0 4px;font-size:30px;line-height:1.2;letter-spacing:-.01em}
        .card{background:linear-gradient(180deg,#eef2f7,#e5eaf1);border:1px solid var(--line);border-radius:var(--radius);padding:18px;box-shadow:var(--shadow);margin-bottom:16px;transition:transform .16s ease, box-shadow .16s ease}
        .card:hover{transform:translateY(-1px);box-shadow:0 14px 28px rgba(15,23,42,.1)}
        .card-title{margin:0 0 12px;font-size:18px}
        .stats-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:14px}
        .stat-card{padding:16px;border-radius:14px;border:1px solid #c9d6ea;background:linear-gradient(180deg,#eef3fa,#e7edf6)}
        .stat-label{font-size:13px;color:var(--muted);margin-bottom:6px}
        .stat-value{font-size:28px;font-weight:700;color:#0b3b9c}
        .form-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:12px}
        .field{display:flex;flex-direction:column;gap:6px}
        .field label{font-size:13px;color:#334155;font-weight:600}
        input,select,button,textarea{height:40px;padding:9px 12px;border:1px solid #b7c3d1;border-radius:10px;font:inherit;background:#f4f7fb}
        textarea{height:auto}
        input:focus,select:focus,textarea:focus{outline:none;border-color:#93c5fd;box-shadow:0 0 0 3px rgba(59,130,246,.18)}
        button{background:var(--primary);color:#fff;border:none;cursor:pointer;font-weight:600;transition:.2s ease}
        button:hover{background:var(--primary-600)}
        .btn-secondary{background:#334155}
        .btn-danger{background:var(--danger)}
        .btn-block{width:100%}
        .table-wrap{overflow:auto;border:1px solid var(--line);border-radius:10px;background:#edf2f7}
        table{width:100%;border-collapse:collapse;min-width:760px;background:#edf2f7}
        th,td{border-bottom:1px solid var(--line);padding:11px 10px;text-align:left;font-size:14px}
        th{background:#dfe7f1;color:#1f2937;font-weight:700}
        tr:hover td{background:#e8eef6}
        .chip{display:inline-flex;padding:4px 8px;border-radius:999px;font-size:12px;background:var(--primary-soft);color:#1d4ed8;font-weight:600}
        .alert{padding:12px;background:#ecfeff;border:1px solid #a5f3fc;color:#0c4a6e;border-radius:10px;margin-bottom:12px}
        .muted{color:var(--muted);font-size:13px}
        .stack{display:flex;flex-direction:column;gap:14px}
        .row{display:flex;gap:10px;align-items:center;flex-wrap:wrap}
        form.inline{display:inline-flex;gap:8px;align-items:center}

        @media (max-width:980px){
            .app-shell{display:flex}
            .sidebar{
                position:fixed;
                top:0;
                left:0;
                width:min(84vw,320px);
                height:100vh;
                z-index:1200;
                border-right:1px solid rgba(255,255,255,.08);
                transform:translateX(-105%);
                transition:transform .22s ease;
                box-shadow:0 18px 34px rgba(0,0,0,.28);
            }
            .app-shell.sidebar-open .sidebar{transform:translateX(0)}
            .sidebar-backdrop{
                position:fixed;
                inset:0;
                background:rgba(15,23,42,.5);
                z-index:1100;
            }
            .app-shell.sidebar-open .sidebar-backdrop{display:block}
            .sidebar-brand{margin-bottom:10px}
            .sidebar-nav{flex-direction:column;flex-wrap:nowrap}
            .sidebar-footer{position:absolute;margin-top:0}
            .app-shell.sidebar-collapsed .sidebar-brand strong,
            .app-shell.sidebar-collapsed .sidebar-footer,
            .app-shell.sidebar-collapsed .sidebar-nav a .nav-label{opacity:1;transform:none;pointer-events:auto}
            .app-shell.sidebar-collapsed .sidebar-nav a{justify-content:flex-start;padding-left:10px;padding-right:12px}
            .main-area{width:100%}
            .top-header{position:sticky;height:64px;padding:0 14px;top:0;z-index:1050}
            .container{margin:18px auto;padding:0 14px}
            .page-title{font-size:26px}
            .sidebar-toggle{display:inline-flex}

            body.login-screen{
                background:
                    linear-gradient(180deg, rgba(14,23,38,.6) 0%, rgba(14,23,38,.34) 45%, rgba(14,23,38,.2) 100%),
                    url('<?= e($loginBackgroundUrl) ?>') 58% center / cover no-repeat;
            }
            .auth-top{padding:14px 14px 0}
            .auth-login-shell{min-height:calc(100vh - 64px);padding:14px 12px 16px;align-items:flex-end}
            .login-card{width:100%;max-width:440px}
        }
    </style>
</head>
<body class="<?= $isLoginBackgroundPage ? 'login-screen' : '' ?>">
<?php $sessionUser = Auth::user() ?? []; ?>
<?php $sessionRole = auth_user_role(); ?>
<?php $sessionDisplayName = $sessionRole === 'owner' ? (string) ($sessionUser['nome_consultorio'] ?? '') : (string) ($sessionUser['team_member_name'] ?? $sessionUser['email'] ?? ''); ?>
<?php
$sessionDisplayName = trim($sessionDisplayName);
if ($sessionDisplayName === '' || strpos($sessionDisplayName, '??') !== false || strpos($sessionDisplayName, '�') !== false) {
    $sessionDisplayName = $sessionRole === 'owner' ? 'Clínica' : 'Usuário';
}
?>
<?php $sessionRoleLabel = $sessionRole === 'owner' ? 'Administrador' : ucfirst($sessionRole); ?>

<?php if ($hasSidebar): ?>
<div class="app-shell" id="appShell">
    <aside class="sidebar">
        <div class="sidebar-brand">
            <span class="brand-badge">AT</span>
            <strong>Atendy</strong>
        </div>

        <nav class="sidebar-nav">
            <?php if (auth_can_access_route('dashboard')): ?><a data-label="Painel" class="<?= $currentRoute === 'dashboard' ? 'active' : '' ?>" href="<?= e(base_url('route=dashboard')) ?>"><span class="nav-label">Painel</span></a><?php endif; ?>
            <?php if (auth_can_access_route('patients')): ?><a data-label="Pacientes" class="<?= $currentRoute === 'patients' ? 'active' : '' ?>" href="<?= e(base_url('route=patients')) ?>"><span class="nav-label">Pacientes</span></a><?php endif; ?>
            <?php if (auth_can_access_route('appointments')): ?><a data-label="Consultas" class="<?= $currentRoute === 'appointments' ? 'active' : '' ?>" href="<?= e(base_url('route=appointments')) ?>"><span class="nav-label">Consultas</span></a><?php endif; ?>
            <?php if (auth_can_access_route('whatsapp')): ?><a data-label="WhatsApp" class="<?= $currentRoute === 'whatsapp' ? 'active' : '' ?>" href="<?= e(base_url('route=whatsapp')) ?>"><span class="nav-label">WhatsApp</span></a><?php endif; ?>
            <?php if (auth_can_access_route('financeiro')): ?><a data-label="Financeiro" class="<?= $currentRoute === 'financeiro' ? 'active' : '' ?>" href="<?= e(base_url('route=financeiro')) ?>"><span class="nav-label">Financeiro</span></a><?php endif; ?>
            <?php if (auth_can_access_route('reports')): ?><a data-label="Relatórios" class="<?= $currentRoute === 'reports' ? 'active' : '' ?>" href="<?= e(base_url('route=reports')) ?>"><span class="nav-label">Relatórios</span></a><?php endif; ?>
            <?php if (auth_can_access_route('team')): ?><a data-label="Equipe" class="<?= $currentRoute === 'team' ? 'active' : '' ?>" href="<?= e(base_url('route=team')) ?>"><span class="nav-label">Equipe</span></a><?php endif; ?>
            <?php if (auth_can_access_route('settings')): ?><a data-label="Configurações" class="<?= $currentRoute === 'settings' ? 'active' : '' ?>" href="<?= e(base_url('route=settings')) ?>"><span class="nav-label">Configurações</span></a><?php endif; ?>
            <?php if (auth_can_access_route('billing')): ?><a data-label="Assinatura" class="<?= in_array($currentRoute, ['billing', 'pricing'], true) ? 'active' : '' ?>" href="<?= e(base_url('route=pricing')) ?>"><span class="nav-label">Assinatura</span></a><?php endif; ?>
            <a data-label="Sair" href="<?= e(base_url('route=logout')) ?>"><span class="nav-label">Sair</span></a>
        </nav>

        <div class="sidebar-footer">Visual organizado · azul e cinza</div>
    </aside>
    <div class="sidebar-backdrop" id="sidebarBackdrop"></div>

    <div class="main-area">
        <div class="top-header">
            <div class="top-header-left">
                <button type="button" id="sidebarToggle" class="sidebar-toggle" title="Recolher menu">=</button>
                <div class="title">Plataforma de automação clínica</div>
            </div>
            <div style="display:flex; align-items:center; gap:16px;">
                <!-- Sino de notificações -->
                <div style="position:relative;">
                    <button type="button" id="notificationBell" style="background:#fff; border:1px solid #cbd5e1; font-size:13px; font-weight:700; cursor:pointer; position:relative; color:#334155; border-radius:8px; height:34px; padding:0 10px;" title="Notificações">
                        Notificações
                        <span id="notificationBadge" style="display:none; position:absolute; top:-6px; right:-8px; background:#dc2626; color:#fff; border-radius:50%; width:20px; height:20px; font-size:11px; font-weight:700; line-height:20px; text-align:center;">0</span>
                    </button>
                    <!-- Dropdown de notificações -->
                    <div id="notificationDropdown" style="display:none; position:absolute; top:36px; right:0; background:#fff; border:1px solid #e2e8f0; border-radius:10px; box-shadow:0 4px 12px rgba(15,23,42,.1); width:320px; max-height:380px; overflow:auto; z-index:1000;">
                        <div style="padding:10px;">
                            <div style="display:flex; justify-content:space-between; align-items:center; padding:8px 0; border-bottom:1px solid #e2e8f0; margin-bottom:8px;">
                                <strong style="font-size:13px;">Notificações</strong>
                                <a href="<?= e(base_url('route=notifications')) ?>" style="color:#2563eb; text-decoration:none; font-size:12px; font-weight:600;">Ver tudo ></a>
                            </div>
                            <div id="notificationList" style="max-height:300px; overflow:auto;"></div>
                        </div>
                    </div>
                </div>
                <div class="muted"><?= e($sessionDisplayName) ?> · <?= e($sessionRoleLabel) ?></div>
            </div>
        </div>
        <div class="container">
        <script>
        (function () {
            var shell = document.getElementById('appShell');
            var toggle = document.getElementById('sidebarToggle');
            var backdrop = document.getElementById('sidebarBackdrop');
            if (!shell || !toggle) return;

            var storageKey = 'atendy_sidebar_collapsed';
            var mobileQuery = window.matchMedia('(max-width: 980px)');

            function isMobile() {
                return mobileQuery.matches;
            }

            function closeMobileMenu() {
                shell.classList.remove('sidebar-open');
                document.body.style.overflow = '';
            }

            function openMobileMenu() {
                shell.classList.add('sidebar-open');
                document.body.style.overflow = 'hidden';
            }

            function restoreDesktopState() {
                closeMobileMenu();
                try {
                    if (window.localStorage.getItem(storageKey) === '1') {
                        shell.classList.add('sidebar-collapsed');
                    } else {
                        shell.classList.remove('sidebar-collapsed');
                    }
                } catch (e) {
                    shell.classList.remove('sidebar-collapsed');
                }
            }

            function prepareMobileState() {
                shell.classList.remove('sidebar-collapsed');
                closeMobileMenu();
            }

            if (isMobile()) {
                prepareMobileState();
            } else {
                restoreDesktopState();
            }

            toggle.addEventListener('click', function () {
                if (isMobile()) {
                    if (shell.classList.contains('sidebar-open')) {
                        closeMobileMenu();
                    } else {
                        openMobileMenu();
                    }
                    return;
                }

                shell.classList.toggle('sidebar-collapsed');
                try {
                    window.localStorage.setItem(storageKey, shell.classList.contains('sidebar-collapsed') ? '1' : '0');
                } catch (e) {}
            });

            if (backdrop) {
                backdrop.addEventListener('click', closeMobileMenu);
            }

            var navLinks = shell.querySelectorAll('.sidebar-nav a');
            Array.prototype.forEach.call(navLinks, function (link) {
                link.addEventListener('click', function () {
                    if (isMobile()) {
                        closeMobileMenu();
                    }
                });
            });

            function handleViewportChange() {
                if (isMobile()) {
                    prepareMobileState();
                } else {
                    restoreDesktopState();
                }
            }

            if (typeof mobileQuery.addEventListener === 'function') {
                mobileQuery.addEventListener('change', handleViewportChange);
            } else if (typeof mobileQuery.addListener === 'function') {
                mobileQuery.addListener(handleViewportChange);
            }
        }());
        </script>

        <!-- Notificações JS -->
        <script>
        (function () {
            var bell = document.getElementById('notificationBell');
            var dropdown = document.getElementById('notificationDropdown');
            var badge = document.getElementById('notificationBadge');
            var list = document.getElementById('notificationList');

            if (!bell || !dropdown) return;

            // Toggle dropdown
            bell.addEventListener('click', function (e) {
                e.stopPropagation();
                dropdown.style.display = dropdown.style.display === 'none' ? 'block' : 'none';
                if (dropdown.style.display === 'block') {
                    loadNotifications();
                }
            });

            // Fechar ao clicar fora
            document.addEventListener('click', function () {
                dropdown.style.display = 'none';
            });

            dropdown.addEventListener('click', function (e) {
                e.stopPropagation();
            });

            function loadNotifications() {
                fetch('<?= e(base_url('route=notifications')) ?>', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'action=get_latest'
                })
                .then(r => r.json())
                .then(d => {
                    if (!d.notifications || d.notifications.length === 0) {
                        list.innerHTML = '<div style="padding:10px; color:#64748b; text-align:center;">Sem notificações</div>';
                        return;
                    }
                    var html = '';
                    d.notifications.forEach(n => {
                        var unread = n.is_read ? '' : ' style="background:#f0fdf4; border-left:3px solid #22c55e;"';
                        html += '<div' + unread + ' style="padding:8px; border-bottom:1px solid #e2e8f0; cursor:pointer;" onclick="markNotificationRead(' + n.id + ')">'
                            + '<strong style="font-size:12px;">' + escapeHtml(n.title) + '</strong>'
                            + '<div style="font-size:11px; color:#64748b; margin-top:3px;">' + escapeHtml(n.message.substring(0, 60)) + '...</div>'
                            + '<div style="font-size:10px; color:#94a3b8; margin-top:2px;">' + timeAgo(n.created_at) + '</div>'
                            + '</div>';
                    });
                    list.innerHTML = html;
                });
                updateBadge();
            }

            function updateBadge() {
                fetch('<?= e(base_url('route=notifications')) ?>', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'action=get_unread_count'
                })
                .then(r => r.json())
                .then(d => {
                    var count = d.unread_count || 0;
                    if (count > 0) {
                        badge.textContent = count > 9 ? '9+' : count;
                        badge.style.display = 'block';
                    } else {
                        badge.style.display = 'none';
                    }
                });
            }

            function escapeHtml(text) {
                var map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
                return text.replace(/[&<>"']/g, m => map[m]);
            }

            function timeAgo(dateStr) {
                var d = new Date(dateStr);
                var now = new Date();
                var seconds = Math.floor((now - d) / 1000);
                if (seconds < 60) return 'agora';
                if (seconds < 3600) return Math.floor(seconds / 60) + 'm atrás';
                if (seconds < 86400) return Math.floor(seconds / 3600) + 'h atrás';
                return Math.floor(seconds / 86400) + 'd atrás';
            }

            window.markNotificationRead = function (id) {
                fetch('<?= e(base_url('route=notifications')) ?>', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'action=mark_read&notification_id=' + id
                })
                .then(() => {
                    loadNotifications();
                });
            };

            // Carregar ao abrir
            updateBadge();
            // Atualizar a cada 30s
            setInterval(updateBadge, 30000);
        })();
        </script>
<?php else: ?>
<div class="auth-top">
    <a href="<?= e(base_url('route=login')) ?>" class="auth-brand">
        <span class="brand-badge">AT</span>
        <span>Atendy</span>
    </a>
</div>
<div class="container">
<?php endif; ?>


