@once
    @push('styles')
        <style>
            .ct-index { display: grid; gap: 18px; width: 100%; }
            .ct-index a { text-decoration: none; }

            .ct-index-header {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 22px;
                padding: 23px 25px;
                border: 1px solid #e2e8f0;
                border-radius: 20px;
                background: #fff;
                box-shadow: 0 6px 18px rgba(15, 23, 42, .05);
            }

            .ct-index-header small { display: block; margin-bottom: 6px; color: #0891b2; font-size: 10px; font-weight: 850; letter-spacing: .1em; text-transform: uppercase; }
            .ct-index-header h1 { margin: 0; color: #0f172a; font-size: clamp(25px, 3vw, 34px); letter-spacing: -.035em; }
            .ct-index-header p { margin: 7px 0 0; max-width: 700px; color: #64748b; font-size: 13px; line-height: 1.55; }
            .ct-index-actions { display: flex; flex-wrap: wrap; gap: 9px; flex: 0 0 auto; }

            .ct-flash { display: flex; align-items: center; gap: 9px; padding: 12px 15px; border: 1px solid; border-radius: 13px; font-size: 12px; font-weight: 750; }
            .ct-flash-success { color: #166534; border-color: #bbf7d0; background: #f0fdf4; }
            .ct-flash-error { color: #991b1b; border-color: #fecaca; background: #fef2f2; }

            .ct-filter-bar {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 14px;
                padding: 15px;
                border-bottom: 1px solid #e8edf4;
                background: #fbfdff;
            }

            .ct-filter-copy strong { display: block; color: #0f172a; font-size: 14px; }
            .ct-filter-copy span { display: block; margin-top: 3px; color: #64748b; font-size: 10px; }
            .ct-filter { display: flex; align-items: center; justify-content: flex-end; gap: 8px; flex: 1; flex-wrap: wrap; }
            .ct-search { position: relative; width: min(330px, 100%); }
            .ct-search i { position: absolute; top: 50%; left: 12px; color: #94a3b8; transform: translateY(-50%); }
            .ct-search input, .ct-filter select {
                width: 100%;
                min-height: 40px;
                border: 1px solid #cbd5e1;
                border-radius: 11px;
                outline: none;
                color: #0f172a;
                background: #fff;
                font-size: 12px;
            }
            .ct-search input { padding: 0 12px 0 36px; }
            .ct-filter select { width: 145px; padding: 0 11px; }
            .ct-search input:focus, .ct-filter select:focus { border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37,99,235,.1); }

            .ct-inline-actions { display: flex; align-items: center; flex-wrap: wrap; gap: 6px; }
            .ct-inline-actions form { margin: 0; }
            .ct-icon-button {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                width: 34px;
                height: 34px;
                border: 1px solid #dbe3ed;
                border-radius: 10px;
                color: #475569;
                background: #fff;
                cursor: pointer;
            }
            .ct-icon-button:hover { color: #1d4ed8; border-color: #bfdbfe; background: #eff6ff; }
            .ct-icon-button.warning:hover { color: #b45309; border-color: #fde68a; background: #fffbeb; }
            .ct-icon-button.danger:hover { color: #b91c1c; border-color: #fecaca; background: #fef2f2; }
            .ct-current-user { display: inline-flex; align-items: center; min-height: 34px; padding: 0 10px; border-radius: 10px; color: #0e7490; background: #ecfeff; font-size: 10px; font-weight: 850; }

            .ct-product-list { display: grid; gap: 4px; }
            .ct-product-list span { display: block; color: #0f172a; font-weight: 700; }
            .ct-product-list small { display: block; color: #64748b; }
            .ct-address { display: block; max-width: 290px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
            .ct-pagination { padding: 14px 16px; border-top: 1px solid #edf2f7; }
            .ct-result-count { color: #64748b; font-size: 11px; }

            @media (max-width: 900px) {
                .ct-index-header { align-items: flex-start; flex-direction: column; }
                .ct-filter-bar { align-items: flex-start; flex-direction: column; }
                .ct-filter { justify-content: flex-start; width: 100%; }
            }

            @media (max-width: 620px) {
                .ct-index-header { padding: 20px 18px; }
                .ct-index-actions, .ct-index-actions .ct-button { width: 100%; }
                .ct-filter { align-items: stretch; flex-direction: column; }
                .ct-search, .ct-filter select, .ct-filter .ct-button { width: 100%; }
                .ct-table th, .ct-table td { padding-left: 11px; padding-right: 11px; }
            }
        </style>
    @endpush
@endonce
