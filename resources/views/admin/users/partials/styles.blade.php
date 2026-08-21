<style>
    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-end;
        gap: 24px;
        margin-bottom: 22px;
        padding: 24px;
        border-radius: 24px;
        background:
            radial-gradient(circle at top left, rgba(34, 211, 238, 0.18), transparent 35%),
            linear-gradient(135deg, #ffffff, #f8fafc);
        border: 1px solid #e5e7eb;
        box-shadow: 0 12px 30px rgba(15, 23, 42, 0.07);
    }

    .eyebrow {
        display: inline-flex;
        background: #ecfeff;
        color: #0891b2;
        border: 1px solid #cffafe;
        border-radius: 999px;
        padding: 7px 12px;
        font-size: 12px;
        font-weight: 800;
        margin-bottom: 12px;
    }

    .page-header h1 {
        margin: 0;
        color: #0f172a;
        font-size: 34px;
        font-weight: 800;
        letter-spacing: -0.9px;
    }

    .page-header p {
        margin: 8px 0 0;
        color: #64748b;
        line-height: 1.6;
    }

    .header-actions {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
    }

    .form-panel {
        background: #ffffff;
        border: 1px solid #e5e7eb;
        border-radius: 24px;
        padding: 24px;
        box-shadow: 0 12px 30px rgba(15, 23, 42, 0.07);
    }

    .form-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 18px;
    }

    .form-group {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }

    .form-group label {
        color: #334155;
        font-size: 13px;
        font-weight: 800;
    }

    .form-group input,
    .form-group select {
        width: 100%;
        border: 1px solid #cbd5e1;
        background: #ffffff;
        border-radius: 14px;
        padding: 12px 14px;
        color: #0f172a;
        outline: none;
        transition: 0.2s ease;
    }

    .form-group input:focus,
    .form-group select:focus {
        border-color: #2563eb;
        box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.12);
    }

    .optional-label {
        color: #94a3b8;
        font-size: 11px;
        margin-left: 6px;
    }

    .note-box {
        background: #f8fafc;
        border: 1px solid #e5e7eb;
        border-radius: 16px;
        padding: 14px;
        justify-content: center;
    }

    .note-box strong {
        color: #0f172a;
    }

    .note-box span {
        color: #64748b;
        font-size: 13px;
        line-height: 1.5;
    }

    .error-text {
        color: #dc2626;
        font-size: 12px;
        font-weight: 700;
    }

    .form-actions {
        display: flex;
        gap: 10px;
        margin-top: 24px;
        flex-wrap: wrap;
    }

    .role-badge,
    .status-badge {
        display: inline-flex;
        padding: 6px 10px;
        border-radius: 999px;
        font-size: 11px;
        font-weight: 800;
    }

    .role-administrator {
        background: #fef3c7;
        color: #b45309;
    }

    .role-driver {
        background: #f0fdf4;
        color: #16a34a;
    }

    .role-receiver {
        background: #f5f3ff;
        color: #7c3aed;
    }

    .role-none {
        background: #f1f5f9;
        color: #64748b;
    }

    .status-active {
        background: #f0fdf4;
        color: #16a34a;
    }

    .status-inactive {
        background: #fef2f2;
        color: #dc2626;
    }

    @media (max-width: 800px) {
        .page-header {
            flex-direction: column;
            align-items: flex-start;
        }

        .form-grid {
            grid-template-columns: 1fr;
        }
    }
</style>
