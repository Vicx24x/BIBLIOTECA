<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;900&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<header class="site-header">
    <div class="header-logo left-logo">
        <img src="logo_ipn.png" alt="IPN">
    </div>
    <div class="header-center">
        <div class="header-title-block">
            <span class="header-eyebrow">Sistema de Gestión</span>
            <h1 class="header-title">Biblioteca Digital</h1>
            <span class="header-subtitle">UPIICSA · Instituto Politécnico Nacional</span>
        </div>
    </div>
    <div class="header-logo right-logo">
        <img src="logo_upiicsa.png" alt="UPIICSA">
    </div>
</header>
<style>
    @import url('https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;900&family=DM+Sans:wght@300;400;500;600&display=swap');
    
    :root {
        --guinda: #850021;
        --guinda-dark: #5a0016;
        --guinda-light: #a8002a;
        --gold: #c9a84c;
        --gold-light: #e8c97e;
        --cream: #fdf8f0;
        --dark: #1a1a2e;
        --text-muted: #6b7280;
        --white: #ffffff;
        --shadow-soft: 0 4px 24px rgba(133,0,33,0.10);
        --shadow-card: 0 8px 32px rgba(0,0,0,0.08);
    }

    * { box-sizing: border-box; }

    .site-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 14px 40px;
        background: linear-gradient(135deg, var(--guinda-dark) 0%, var(--guinda) 60%, var(--guinda-light) 100%);
        border-bottom: 3px solid var(--gold);
        position: relative;
        overflow: hidden;
    }

    .site-header::before {
        content: '';
        position: absolute;
        inset: 0;
        background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.03'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
        pointer-events: none;
    }

    .header-logo img {
        height: 70px;
        width: auto;
        filter: drop-shadow(0 2px 8px rgba(0,0,0,0.3));
        transition: transform 0.3s ease;
    }
    .header-logo img:hover { transform: scale(1.05); }

    .header-center { text-align: center; flex: 1; }
    .header-eyebrow {
        display: block;
        font-family: 'DM Sans', sans-serif;
        font-size: 0.7rem;
        font-weight: 500;
        letter-spacing: 3px;
        text-transform: uppercase;
        color: var(--gold-light);
        margin-bottom: 2px;
        opacity: 0.9;
    }
    .header-title {
        font-family: 'Playfair Display', serif;
        font-size: 2.2rem;
        font-weight: 900;
        color: var(--white);
        margin: 0;
        line-height: 1.1;
        text-shadow: 0 2px 12px rgba(0,0,0,0.3);
        letter-spacing: -0.5px;
    }
    .header-subtitle {
        display: block;
        font-family: 'DM Sans', sans-serif;
        font-size: 0.85rem;
        font-weight: 300;
        color: rgba(255,255,255,0.75);
        margin-top: 4px;
        letter-spacing: 1px;
    }
</style>
