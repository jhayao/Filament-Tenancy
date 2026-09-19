<style>
    body.lw-standalone-page {
        background: #f8fafc !important;
        color: #111827;
    }

    body.lw-standalone-page .fi-simple-layout {
        min-height: 100dvh;
        background: #f8fafc;
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
        background: conic-gradient(#4f8df7 0deg 100deg, #cfe0ff 100deg 360deg);
        animation: lw-spinner-rotate 900ms linear infinite;
    }

    .lw-spinner::after {
        content: "";
        position: absolute;
        inset: 10px;
        border-radius: inherit;
        background: #f8fafc;
    }

    .lw-page-heading {
        margin: 0;
        color: #111827;
        font-size: 24px;
        font-weight: 700;
        letter-spacing: -0.02em;
        line-height: 1.25;
    }

    .lw-page-supporting-text {
        max-width: 560px;
        margin: 10px auto 0;
        color: #667085;
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
        outline: 3px solid #cfe0ff;
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
        color-scheme: light;
    }

    .lw-registration-form .fi-input-wrp {
        background: #fff;
        box-shadow: 0 0 0 1px #cbd5e1;
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
        color: #111827;
        caret-color: #111827;
        -webkit-text-fill-color: #111827;
    }

    .lw-registration-form input.fi-input::placeholder {
        color: #667085;
        -webkit-text-fill-color: #667085;
        opacity: 1;
    }

    .lw-registration-form .fi-fo-field-label-content {
        color: #111827;
    }

    .lw-registration-form .fi-sc-text,
    .lw-registration-form .fi-input-wrp-label {
        color: #667085;
    }

    .lw-registration-form .fi-fo-field-wrp-error-message,
    .lw-registration-form .fi-fo-field-label-required-mark {
        color: #b42318;
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
