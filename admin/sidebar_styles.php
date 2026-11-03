<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<style>
    @view-transition { navigation: auto; }
    ::view-transition-old(root) { animation: admin-page-out .16s ease both; }
    ::view-transition-new(root) { animation: admin-page-in .24s ease both; }
    @keyframes admin-page-out { to { opacity: 0; transform: translateY(-4px); } }
    @keyframes admin-page-in { from { opacity: 0; transform: translateY(6px); } }
    @media (prefers-reduced-motion: reduce) {
        @view-transition { navigation: none; }
        ::view-transition-old(root),
        ::view-transition-new(root) { animation: none; }
    }
</style>
<style>
    .admin-sidebar { display: none; }
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
            transition: background .16s ease, color .16s ease;
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