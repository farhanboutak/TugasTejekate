<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<style>
    @view-transition { navigation: auto; }
    ::view-transition-old(root) { animation: admin-page-out .16s ease both; }
    ::view-transition-new(root) { animation: admin-page-in .24s ease both; }
    ::view-transition-old(admin-sidebar),
    ::view-transition-new(admin-sidebar),
    ::view-transition-old(admin-mobile-nav),
    ::view-transition-new(admin-mobile-nav) { animation: none; }
    @keyframes admin-page-out { to { opacity: 0; transform: translateY(-4px); } }
    @keyframes admin-page-in { from { opacity: 0; transform: translateY(6px); } }
    @media (prefers-reduced-motion: reduce) {
        @view-transition { navigation: none; }
        ::view-transition-old(root),
        ::view-transition-new(root) { animation: none; }
    }
</style>
<style>
    .admin-sidebar,
    .admin-mobile-nav { display: none; }
    .admin-mobile-nav { view-transition-name: admin-mobile-nav; }
    .admin-sidebar-nav a,
    .admin-sidebar-logout,
    .admin-mobile-nav a { transition:none !important; animation:none !important; }
    @media (max-width: 860px) {
        body { padding-bottom: 78px !important; }
        body > .navbar-custom .container > .d-flex,
        body > .topbar .topbar-user,
        body > .topbar .top-actions,
        body > .topbar .nav-links,
        body > .topbar .side-links,
        body > .topbar .mobile-nav,
        body > .topbar .back { display: none !important; }
        .admin-mobile-nav {
            position: fixed;
            inset: auto 0 0;
            z-index: 1200;
            min-height: 66px;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(42px, 1fr));
            align-items: stretch;
            padding: 5px 4px calc(5px + env(safe-area-inset-bottom));
            border-top: 1px solid #e7e9e3;
            background: rgba(255,255,255,.97);
            box-shadow: 0 -7px 22px rgba(29,55,62,.09);
            backdrop-filter: blur(12px);
        }
        .admin-mobile-nav a {
            min-width: 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 3px;
            border-radius: 8px;
            color: #647587;
            font-size: 8px;
            font-weight: 700;
            text-decoration: none;
        }
        .admin-mobile-nav i { font-size: 15px; line-height: 1; }
        .admin-mobile-nav a:hover,
        .admin-mobile-nav a[aria-current="page"] { background: #e8f4ef; color: #105653; }
    }
    @media (min-width: 992px) {
        body { padding-left: 250px !important; }
        body > .navbar-custom,
        body > .topbar { display: none !important; }
        .admin-sidebar {
            position: fixed;
            inset: 0 auto 0 0;
            z-index: 1100;
            width: 250px;
            height: 100vh;
            display: flex;
            flex-direction: column;
            view-transition-name: admin-sidebar;
            padding: 24px 18px;
            background: #146b68;
            color: #fff;
            box-shadow: 8px 0 24px rgba(16, 86, 83, .12);
        }
        .admin-sidebar-brand {
            display: flex;
            align-items: center;
            gap: 11px;
            color: #fff;
            font-size: 15px;
            font-weight: 800;
            letter-spacing: .04em;
            text-decoration: none;
        }
        .admin-sidebar-brand small {
            display: block;
            margin-top: 3px;
            color: rgba(255,255,255,.7);
            font-size: 9px;
            font-weight: 600;
            letter-spacing: .08em;
        }
        .admin-sidebar-mark {
            width: 38px;
            height: 38px;
            display: grid;
            flex: 0 0 auto;
            place-items: center;
            border: 1px solid rgba(255,255,255,.25);
            border-radius: 11px;
            background: rgba(255,255,255,.12);
        }
        .admin-sidebar-user {
            display: flex;
            align-items: center;
            gap: 9px;
            min-width: 0;
            margin: 28px 0 14px;
            padding: 10px 11px;
            border-radius: 9px;
            background: rgba(255,255,255,.12);
            color: rgba(255,255,255,.92);
            font-size: 11px;
            font-weight: 650;
        }
        .admin-sidebar-user span { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .admin-sidebar-nav { display: grid; gap: 5px; }
        .admin-sidebar-nav a,
        .admin-sidebar-logout {
            min-height: 42px;
            display: flex;
            align-items: center;
            gap: 11px;
            padding: 0 12px;
            border: 1px solid transparent;
            border-radius: 9px;
            color: rgba(255,255,255,.84);
            font-size: 12px;
            font-weight: 700;
            text-decoration: none;
            transition: none;
        }
        .admin-sidebar-nav a:hover,
        .admin-sidebar-nav a[aria-current="page"] {
            border-color: rgba(255,255,255,.16);
            background: rgba(255,255,255,.15);
            color: #fff;
        }
        .admin-sidebar-nav i,
        .admin-sidebar-logout i { width: 16px; font-size: 15px; text-align: center; }
        .admin-sidebar-mark i { font-size: 16px; }
        .admin-sidebar-logout {
            margin-top: auto;
            border-color: rgba(255,255,255,.25);
        }
        .admin-sidebar-logout:hover { background: #fff; color: #146b68; }
    }
</style>