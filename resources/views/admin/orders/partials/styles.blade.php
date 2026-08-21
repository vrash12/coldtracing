<style>
    :root {
        --ct-primary: #2563eb;
        --ct-primary-dark: #1d4ed8;
        --ct-cyan: #06b6d4;
        --ct-success: #16a34a;
        --ct-warning: #d97706;
        --ct-danger: #dc2626;
        --ct-violet: #7c3aed;
        --ct-text: #0f172a;
        --ct-muted: #64748b;
        --ct-soft: #f8fafc;
        --ct-border: #e5e7eb;
        --ct-white: #ffffff;
        --ct-shadow: 0 12px 30px rgba(15, 23, 42, 0.07);
        --ct-shadow-soft: 0 10px 26px rgba(15, 23, 42, 0.06);
    }

    .orders-page {
        display: flex;
        flex-direction: column;
        gap: 20px;
    }

    .page-toolbar,
    .panel-top,
    .header-actions,
    .actions,
    .form-actions,
    .order-items-header,
    .location-picker-header,
    .section-header {
        display: flex;
        gap: 14px;
    }

    .page-toolbar,
    .panel-top,
    .order-items-header,
    .location-picker-header {
        justify-content: space-between;
        align-items: center;
    }

    .page-toolbar h1 {
        margin: 0;
        color: var(--ct-text);
        font-size: 30px;
        font-weight: 900;
        letter-spacing: -0.05em;
    }

    .header-actions,
    .actions,
    .form-actions {
        align-items: center;
        flex-wrap: wrap;
    }

    .primary-button,
    .secondary-button,
    .filter-form button,
    .clear-button,
    .action-btn,
    .add-product-button,
    .map-search-button {
        border: none;
        text-decoration: none;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-weight: 900;
        transition: 0.2s ease;
    }

    .primary-button {
        min-height: 44px;
        padding: 0 18px;
        border-radius: 999px;
        background: var(--ct-primary);
        color: var(--ct-white);
        box-shadow: 0 10px 20px rgba(37, 99, 235, 0.2);
    }

    .primary-button:hover {
        background: var(--ct-primary-dark);
        transform: translateY(-1px);
    }

    .secondary-button,
    .clear-button {
        min-height: 44px;
        padding: 0 18px;
        border-radius: 999px;
        background: #f1f5f9;
        color: #475569;
    }

    .secondary-button:hover,
    .clear-button:hover {
        background: #e2e8f0;
    }

    .flash-message,
    .warning-box {
        padding: 14px 16px;
        border-radius: 16px;
        font-size: 14px;
        font-weight: 800;
    }

    .flash-message.success {
        background: #f0fdf4;
        color: #15803d;
        border: 1px solid #bbf7d0;
    }

    .flash-message.error {
        background: #fef2f2;
        color: #b91c1c;
        border: 1px solid #fecaca;
    }

    .warning-box {
        margin-bottom: 14px;
        background: #fffbeb;
        color: #92400e;
        border: 1px solid #fde68a;
    }

    .order-stats {
        display: grid;
        grid-template-columns: repeat(6, minmax(0, 1fr));
        gap: 14px;
    }

    .stat-card {
        background: var(--ct-white);
        border: 1px solid var(--ct-border);
        border-radius: 20px;
        padding: 17px;
        box-shadow: var(--ct-shadow-soft);
    }

    .stat-card span {
        display: block;
        color: var(--ct-muted);
        font-size: 12px;
        font-weight: 900;
        margin-bottom: 8px;
    }

    .stat-card strong {
        display: block;
        color: var(--ct-primary);
        font-size: 28px;
        font-weight: 900;
        line-height: 1;
    }

    .stat-card.danger strong {
        color: var(--ct-danger);
    }

    .orders-panel,
    .form-panel,
    .details-panel,
    .form-section-card,
    .sticky-actions {
        background: var(--ct-white);
        border: 1px solid var(--ct-border);
        box-shadow: var(--ct-shadow);
    }

    .orders-panel,
    .form-panel,
    .details-panel {
        border-radius: 24px;
        padding: 22px;
    }

    .panel-top {
        margin-bottom: 18px;
    }

    .panel-top h2 {
        margin: 0;
        color: var(--ct-text);
        font-size: 20px;
        font-weight: 900;
        letter-spacing: -0.03em;
    }

    .filter-form {
        display: flex;
        align-items: center;
        gap: 9px;
        flex-wrap: wrap;
    }

    .search-input-wrap {
        position: relative;
    }

    .search-input-wrap.full {
        width: 100%;
    }

    .search-input-wrap i {
        position: absolute;
        top: 50%;
        left: 13px;
        transform: translateY(-50%);
        color: #94a3b8;
        font-size: 14px;
        pointer-events: none;
    }

    .search-input-wrap input,
    .filter-form select,
    .form-group input,
    .form-group select,
    .form-group textarea {
        width: 100%;
        border: 1px solid #cbd5e1;
        background: var(--ct-white);
        color: var(--ct-text);
        outline: none;
        transition: 0.2s ease;
    }

    .search-input-wrap input,
    .filter-form select {
        height: 44px;
        border-color: #e2e8f0;
        background: var(--ct-soft);
        border-radius: 999px;
        font-size: 14px;
    }

    .search-input-wrap input {
        min-width: 260px;
        padding: 0 15px 0 38px;
    }

    .filter-form select {
        padding: 0 14px;
    }

    .filter-form button {
        min-height: 44px;
        border-radius: 999px;
        padding: 0 16px;
        background: var(--ct-primary);
        color: var(--ct-white);
        box-shadow: 0 10px 20px rgba(37, 99, 235, 0.2);
    }

    .search-input-wrap input:focus,
    .filter-form select:focus,
    .form-group input:focus,
    .form-group select:focus,
    .form-group textarea:focus {
        border-color: var(--ct-primary);
        background: var(--ct-white);
        box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.12);
    }

    .table-card {
        background: var(--ct-white);
        border: 1px solid var(--ct-border);
        border-radius: 20px;
        overflow: hidden;
    }

    .table-wrapper {
        width: 100%;
        overflow-x: auto;
    }

    .orders-table {
        width: 100%;
        border-collapse: collapse;
        background: var(--ct-white);
    }

    .orders-table th,
    .orders-table td {
        padding: 16px;
        text-align: left;
        vertical-align: middle;
        white-space: nowrap;
    }

    .orders-table th {
        background: var(--ct-soft);
        color: var(--ct-muted);
        font-size: 12px;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        border-bottom: 1px solid var(--ct-border);
    }

    .orders-table td {
        color: #334155;
        font-size: 14px;
        border-bottom: 1px solid #f1f5f9;
    }

    .orders-table tbody tr:hover {
        background: var(--ct-soft);
    }

    .orders-table tbody tr:last-child td {
        border-bottom: none;
    }

    .main-cell,
    .date-cell,
    .route-cell {
        display: flex;
        flex-direction: column;
        gap: 4px;
    }

    .main-cell strong,
    .date-cell strong {
        color: var(--ct-text);
        font-size: 14px;
        font-weight: 900;
    }

    .main-cell small,
    .date-cell small,
    .route-cell small {
        color: #94a3b8;
        font-size: 12px;
    }

    .route-cell {
        max-width: 260px;
        white-space: normal;
    }

    .route-cell span {
        color: #334155;
        line-height: 1.45;
    }

    .muted {
        color: #94a3b8;
        font-weight: 700;
    }

    .status-badge,
    .action-btn {
        border-radius: 999px;
        white-space: nowrap;
    }

    .status-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 30px;
        padding: 7px 11px;
        font-size: 12px;
        font-weight: 900;
    }

    .status-pending {
        background: #fffbeb;
        color: var(--ct-warning);
    }

    .status-approved {
        background: #dbeafe;
        color: var(--ct-primary);
    }

    .status-assigned {
        background: #ecfeff;
        color: #0891b2;
    }

    .status-in_transit {
        background: #f5f3ff;
        color: var(--ct-violet);
    }

    .status-delivered {
        background: #f0fdf4;
        color: var(--ct-success);
    }

    .status-cancelled {
        background: #fef2f2;
        color: var(--ct-danger);
    }

    .action-column {
        text-align: right;
    }

    .actions {
        justify-content: flex-end;
        gap: 8px;
    }

    .actions form {
        margin: 0;
    }

    .action-btn {
        min-height: 34px;
        padding: 8px 12px;
        font-size: 12px;
    }

    .view-btn {
        background: #f1f5f9;
        color: #475569;
    }

    .edit-btn {
        background: #dbeafe;
        color: var(--ct-primary);
    }

    .cancel-btn {
        background: #fffbeb;
        color: var(--ct-warning);
    }

    .delete-btn {
        background: #fef2f2;
        color: var(--ct-danger);
    }

    .empty-state {
        padding: 38px 20px;
        text-align: center;
        color: var(--ct-muted);
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 7px;
    }

    .empty-state i {
        width: 54px;
        height: 54px;
        border-radius: 18px;
        background: #eff6ff;
        color: var(--ct-primary);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 24px;
        margin-bottom: 6px;
    }

    .empty-state strong {
        color: var(--ct-text);
        font-size: 15px;
        font-weight: 900;
    }

    .empty-state span {
        color: var(--ct-muted);
        font-size: 13px;
    }

    .pagination-box {
        margin-top: 18px;
    }

    .order-form-wrapper {
        display: flex;
        flex-direction: column;
        gap: 20px;
    }

    .form-section-card {
        border-radius: 22px;
        padding: 22px;
    }

    .section-header {
        align-items: flex-start;
        margin-bottom: 18px;
    }

    .section-icon {
        width: 46px;
        height: 46px;
        border-radius: 16px;
        background: linear-gradient(135deg, var(--ct-primary), var(--ct-cyan));
        color: var(--ct-white);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 20px;
        flex-shrink: 0;
    }

    .section-header h2 {
        margin: 0;
        color: var(--ct-text);
        font-size: 19px;
        font-weight: 900;
        letter-spacing: -0.03em;
    }

    .section-header p {
        margin: 5px 0 0;
        color: var(--ct-muted);
        font-size: 13px;
        line-height: 1.5;
    }

    .form-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 18px;
    }

    .compact-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .form-group {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }

    .form-group.full-width {
        grid-column: 1 / -1;
    }

    .form-group label {
        color: #334155;
        font-size: 13px;
        font-weight: 900;
    }

    .form-group input,
    .form-group select,
    .form-group textarea {
        border-radius: 14px;
        padding: 12px 14px;
        resize: vertical;
    }

    .error-text {
        color: var(--ct-danger);
        font-size: 12px;
        font-weight: 800;
        margin-top: 8px;
        display: block;
    }

    .order-items-card {
        background: var(--ct-soft);
        border: 1px solid var(--ct-border);
        border-radius: 18px;
        padding: 16px;
    }

    .order-items-header {
        margin-bottom: 14px;
    }

    .order-items-header strong {
        display: block;
        color: var(--ct-text);
        font-size: 15px;
        font-weight: 900;
    }

    .order-items-header span {
        display: block;
        color: var(--ct-muted);
        font-size: 12px;
        margin-top: 3px;
    }

    .order-item-row {
        display: grid;
        grid-template-columns: minmax(220px, 1fr) 150px 160px auto;
        gap: 12px;
        align-items: end;
        background: var(--ct-white);
        border: 1px solid var(--ct-border);
        border-radius: 16px;
        padding: 14px;
        margin-bottom: 12px;
    }

    .order-item-row:last-child {
        margin-bottom: 0;
    }

    .add-product-button {
        border-radius: 999px;
        padding: 10px 14px;
        background: var(--ct-primary);
        color: var(--ct-white);
        font-size: 13px;
        gap: 7px;
        box-shadow: 0 10px 20px rgba(37, 99, 235, 0.2);
    }

    .add-product-button:hover {
        background: var(--ct-primary-dark);
    }

    .remove-product-button {
        width: 44px;
        height: 44px;
        border: none;
        border-radius: 14px;
        background: #fef2f2;
        color: var(--ct-danger);
        cursor: pointer;
        font-size: 16px;
        transition: 0.2s ease;
    }

    .remove-product-button:hover {
        background: #fee2e2;
    }

    .map-search-row {
        display: grid;
        grid-template-columns: minmax(0, 1fr) auto;
        gap: 10px;
        margin-bottom: 14px;
    }

    .map-search-button {
        min-height: 44px;
        border-radius: 14px;
        padding: 0 18px;
        background: var(--ct-primary);
        color: var(--ct-white);
    }

    .map-search-button:hover {
        background: var(--ct-primary-dark);
    }

    .location-picker-card {
        border: 1px solid var(--ct-border);
        border-radius: 20px;
        overflow: hidden;
        background: var(--ct-white);
    }

    .location-picker-header {
        padding: 14px 16px;
        border-bottom: 1px solid var(--ct-border);
        background: var(--ct-soft);
    }

    .location-picker-header strong {
        display: block;
        color: var(--ct-text);
        font-size: 14px;
        font-weight: 900;
    }

    .location-picker-header span {
        display: block;
        color: var(--ct-muted);
        font-size: 12px;
        margin-top: 4px;
        line-height: 1.5;
    }

    #deliveryMap {
        width: 100%;
        height: 420px;
    }

    .delivery-marker {
        width: 44px;
        height: 44px;
        border-radius: 999px;
        background: linear-gradient(135deg, var(--ct-primary), var(--ct-cyan));
        color: var(--ct-white);
        border: 3px solid var(--ct-white);
        box-shadow: 0 10px 24px rgba(15, 23, 42, 0.28);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 20px;
    }

    .sticky-actions {
        border-radius: 18px;
        padding: 14px;
        margin-top: 24px;
    }

    .order-profile {
        display: flex;
        align-items: center;
        gap: 18px;
        margin-bottom: 24px;
    }

    .order-icon {
        width: 72px;
        height: 72px;
        border-radius: 24px;
        background: linear-gradient(135deg, var(--ct-primary), var(--ct-cyan));
        color: var(--ct-white);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 30px;
        flex-shrink: 0;
    }

    .order-profile h2 {
        margin: 0;
        color: var(--ct-text);
        font-size: 26px;
        font-weight: 900;
        letter-spacing: -0.04em;
    }

    .order-profile p {
        margin: 6px 0 12px;
        color: var(--ct-muted);
    }

    .details-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 16px;
    }

    .detail-card {
        background: var(--ct-soft);
        border: 1px solid var(--ct-border);
        border-radius: 18px;
        padding: 16px;
    }

    .detail-card span {
        display: block;
        color: var(--ct-muted);
        font-size: 12px;
        font-weight: 900;
        margin-bottom: 8px;
    }

    .detail-card strong {
        display: block;
        color: var(--ct-text);
        font-size: 14px;
        line-height: 1.5;
    }

    .detail-card small {
        display: block;
        color: #94a3b8;
        margin-top: 6px;
        line-height: 1.5;
    }

    @media (max-width: 1100px) {
        .order-stats {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }

        .panel-top {
            align-items: stretch;
            flex-direction: column;
        }

        .filter-form {
            width: 100%;
        }
    }

    @media (max-width: 900px) {
        .order-item-row,
        .compact-grid,
        .form-grid,
        .details-grid {
            grid-template-columns: 1fr;
        }

        .remove-product-button {
            width: 100%;
        }

        .order-items-header,
        .map-search-row {
            align-items: stretch;
            grid-template-columns: 1fr;
        }

        .order-items-header {
            flex-direction: column;
        }

        .add-product-button,
        .map-search-button {
            width: 100%;
        }
    }

    @media (max-width: 800px) {
        .page-toolbar,
        .order-profile {
            align-items: flex-start;
            flex-direction: column;
        }

        .page-toolbar .primary-button,
        .page-toolbar .secondary-button,
        .header-actions,
        .header-actions a,
        .filter-form,
        .search-input-wrap,
        .search-input-wrap input,
        .filter-form select,
        .filter-form button,
        .clear-button {
            width: 100%;
        }

        .order-stats {
            grid-template-columns: 1fr;
        }

        .orders-panel,
        .form-panel,
        .details-panel,
        .form-section-card {
            padding: 16px;
            border-radius: 20px;
        }

        #deliveryMap {
            height: 360px;
        }
    }

    @media (max-width: 520px) {
        .page-toolbar h1 {
            font-size: 26px;
        }

        .orders-table th,
        .orders-table td {
            padding: 13px;
        }

        .section-header {
            flex-direction: column;
        }
    }
</style>