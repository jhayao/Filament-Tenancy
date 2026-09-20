<style>
    body.lw-standalone-page {
        --lw-page-background: #f8fafc;
        --lw-page-foreground: #111827;
        --lw-page-muted: #667085;
        --lw-input-background: #fff;
        --lw-input-border: #cbd5e1;
        --lw-spinner-track: #cfe0ff;
        --lw-focus: #cfe0ff;
        --lw-error: #b42318;
        background: var(--lw-page-background) !important;
        color: var(--lw-page-foreground);
        color-scheme: light;
    }

    html.dark body.lw-standalone-page {
        --lw-page-background: #111827;
        --lw-page-foreground: #f9fafb;
        --lw-page-muted: #9ca3af;
        --lw-input-background: #1f2937;
        --lw-input-border: #4b5563;
        --lw-spinner-track: #374151;
        --lw-focus: #93c5fd;
        --lw-error: #fca5a5;
        color-scheme: dark;
    }

    body.lw-standalone-page .fi-simple-layout {
        min-height: 100dvh;
        background: var(--lw-page-background);
    }

    body.lw-standalone-page .fi-simple-layout-header {
        display: none;
    }

    body.lw-standalone-page .fi-simple-main-ctn {
        min-height: 100dvh;
        align-items: center;
    }

    body.lw-standalone-page .fi-simple-main {
        width: 100%;
        max-width: none !important;
        min-height: 100dvh;
        margin: 0;
        padding: 0 24px;
        background: transparent;
        border-radius: 0;
        box-shadow: none;
        outline: none;
    }

    body.lw-standalone-page .fi-simple-page,
    body.lw-standalone-page .fi-simple-page-content {
        width: 100%;
    }

    .lw-provisioning-page,
    .lw-registration-page {
        display: flex;
        width: 100%;
        min-height: 100dvh;
        align-items: center;
        justify-content: center;
        padding: 48px 0;
        font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
    }

    .lw-provisioning-state,
    .lw-registration-content {
        width: min(100%, 560px);
        text-align: center;
    }

    .lw-spinner {
        width: 64px;
        height: 64px;
        margin: 0 auto 26px;
        position: relative;
        border-radius: 999px;
        background: conic-gradient(#4f8df7 0deg 100deg, var(--lw-spinner-track) 100deg 360deg);
        animation: lw-spinner-rotate 900ms linear infinite;
    }

    .lw-spinner::after {
        content: "";
        position: absolute;
        inset: 10px;
        border-radius: inherit;
        background: var(--lw-page-background);
    }

    .lw-page-heading {
        margin: 0;
        color: var(--lw-page-foreground);
        font-size: 24px;
        font-weight: 700;
        letter-spacing: -0.02em;
        line-height: 1.25;
    }

    .lw-page-supporting-text {
        max-width: 560px;
        margin: 10px auto 0;
        color: var(--lw-page-muted);
        font-size: 18px;
        font-weight: 400;
        line-height: 1.5;
    }

    .lw-primary-action {
        display: inline-flex;
        min-height: 44px;
        margin-top: 28px;
        align-items: center;
        justify-content: center;
        border: 0;
        border-radius: 8px;
        padding: 0 18px;
        background: #4f8df7;
        color: #fff;
        cursor: pointer;
        font: inherit;
        font-size: 15px;
        font-weight: 600;
        text-decoration: none;
        transition: background-color 150ms ease;
    }

    .lw-primary-action:hover {
        background: #3978e8;
    }

    .lw-primary-action:focus-visible {
        outline: 3px solid var(--lw-focus);
        outline-offset: 3px;
    }

    .lw-registration-content {
        text-align: left;
    }

    .lw-registration-copy {
        margin: 0 auto 32px;
        text-align: center;
    }

    .lw-registration-form {
        width: 100%;
        color-scheme: inherit;
    }

    .lw-registration-form .fi-input-wrp {
        background: var(--lw-input-background);
        box-shadow: 0 0 0 1px var(--lw-input-border);
    }

    .lw-registration-form .fi-input-wrp:focus-within {
        box-shadow: 0 0 0 2px #4f8df7;
    }

    .lw-registration-form .fi-input-wrp.fi-invalid {
        box-shadow: 0 0 0 1px #b42318;
    }

    .lw-registration-form .fi-input-wrp.fi-invalid:focus-within {
        box-shadow: 0 0 0 2px #b42318;
    }

    .lw-registration-form input.fi-input {
        color: var(--lw-page-foreground);
        caret-color: var(--lw-page-foreground);
        -webkit-text-fill-color: var(--lw-page-foreground);
    }

    .lw-registration-form input.fi-input::placeholder {
        color: var(--lw-page-muted);
        -webkit-text-fill-color: var(--lw-page-muted);
        opacity: 1;
    }

    .lw-registration-form .fi-fo-field-label-content {
        color: var(--lw-page-foreground);
    }

    .lw-registration-form .fi-sc-text,
    .lw-registration-form .fi-input-wrp-label {
        color: var(--lw-page-muted);
    }

    .lw-registration-form .fi-fo-field-wrp-error-message,
    .lw-registration-form .fi-fo-field-label-required-mark {
        color: var(--lw-error);
    }

    .lw-registration-form .fi-btn {
        width: 100%;
        justify-content: center;
    }

    @keyframes lw-spinner-rotate {
        to {
            transform: rotate(360deg);
        }
    }

    @media (prefers-reduced-motion: reduce) {
        .lw-spinner {
            animation: none;
        }

        .lw-primary-action {
            transition: none;
        }
    }

    @media (max-width: 640px) {
        .lw-provisioning-page,
        .lw-registration-page {
            padding: 32px 4px;
        }

        .lw-page-heading {
            font-size: 23px;
        }

        .lw-page-supporting-text {
            font-size: 17px;
        }
    }
</style>
