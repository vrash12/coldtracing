@once
    @push('styles')
        <style>
            .ct-dashboard,
            .ct-index {
                --ct-ink: #0f172a;
                --ct-copy: #334155;
                --ct-muted: #64748b;
                --ct-line: #e2e8f0;
                --ct-soft: #f8fafc;
                color: var(--ct-ink);
            }

            .ct-dashboard {
                display: grid;
                gap: 20px;
                width: 100%;
            }

            .ct-dashboard a { text-decoration: none; }

            .ct-hero {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 24px;
                min-height: 180px;
                padding: 30px 32px;
                overflow: hidden;
                border-radius: 24px;
                color: #fff;
                background:
                    radial-gradient(circle at 85% 10%, rgba(34, 211, 238, .24), transparent 32%),
                    linear-gradient(135deg, #0f2942 0%, #0f3f5e 55%, #0e7490 100%);
                box-shadow: 0 16px 38px rgba(15, 41, 66, .18);
            }

            .ct-hero-copy { max-width: 720px; }
            .ct-eyebrow {
                display: inline-flex;
                align-items: center;
                gap: 8px;
                margin-bottom: 10px;
                color: #a5f3fc;
                font-size: 12px;
                font-weight: 800;
                letter-spacing: .11em;
                text-transform: uppercase;
            }

            .ct-eyebrow-dot {
                width: 8px;
                height: 8px;
                border-radius: 999px;
                background: #22d3ee;
                box-shadow: 0 0 0 5px rgba(34, 211, 238, .15);
            }

            .ct-hero h1 {
                margin: 0;
                max-width: 780px;
                font-size: clamp(28px, 3vw, 42px);
                line-height: 1.08;
                letter-spacing: -.035em;
            }

            .ct-hero p {
                margin: 12px 0 0;
                max-width: 670px;
                color: #dbeafe;
                font-size: 15px;
                line-height: 1.7;
            }

            .ct-hero-meta {
                display: flex;
                flex-wrap: wrap;
                gap: 10px 18px;
                margin-top: 17px;
                color: #e0f2fe;
                font-size: 13px;
                font-weight: 650;
            }

            .ct-hero-meta span { display: inline-flex; align-items: center; gap: 7px; }
            .ct-actions { display: flex; flex-wrap: wrap; justify-content: flex-end; gap: 10px; }

            .ct-button {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                gap: 9px;
                min-height: 44px;
                padding: 0 17px;
                border: 1px solid transparent;
                border-radius: 12px;
                font-size: 13px;
                font-weight: 800;
                cursor: pointer;
                transition: transform .18s ease, background .18s ease, border-color .18s ease;
            }

            .ct-button:hover { transform: translateY(-1px); }
            .ct-button-primary { color: #0f2942; background: #fff; }
            .ct-button-secondary { color: #fff; background: rgba(255,255,255,.1); border-color: rgba(255,255,255,.3); }
            .ct-button-dark { color: #fff; background: #0f2942; }
            .ct-button-light { color: #1d4ed8; background: #eff6ff; border-color: #bfdbfe; }
            .ct-button-success { color: #fff; background: #15803d; }
            .ct-button-danger { color: #b91c1c; background: #fef2f2; border-color: #fecaca; }
            .ct-button-small { min-height: 36px; padding: 0 12px; font-size: 12px; }

            .ct-metrics {
                display: grid;
                grid-template-columns: repeat(4, minmax(0, 1fr));
                gap: 14px;
            }

            .ct-metric {
                --tone: #2563eb;
                --tone-soft: #eff6ff;
                position: relative;
                display: flex;
                align-items: center;
                gap: 14px;
                min-width: 0;
                padding: 18px;
                border: 1px solid var(--ct-line);
                border-radius: 18px;
                color: var(--ct-ink);
                background: #fff;
                box-shadow: 0 5px 16px rgba(15, 23, 42, .055);
            }

            a.ct-metric { transition: border-color .18s ease, transform .18s ease, box-shadow .18s ease; }
            a.ct-metric:hover { transform: translateY(-2px); border-color: color-mix(in srgb, var(--tone) 45%, white); box-shadow: 0 12px 24px rgba(15,23,42,.09); }
            .ct-tone-cyan { --tone: #0e7490; --tone-soft: #ecfeff; }
            .ct-tone-green { --tone: #15803d; --tone-soft: #f0fdf4; }
            .ct-tone-amber { --tone: #b45309; --tone-soft: #fffbeb; }
            .ct-tone-red { --tone: #b91c1c; --tone-soft: #fef2f2; }
            .ct-tone-violet { --tone: #7c3aed; --tone-soft: #f5f3ff; }

            .ct-metric-icon {
                display: grid;
                place-items: center;
                width: 45px;
                height: 45px;
                flex: 0 0 45px;
                border-radius: 13px;
                color: var(--tone);
                background: var(--tone-soft);
                font-size: 20px;
            }

            .ct-metric-copy { display: grid; min-width: 0; }
            .ct-metric-copy > span { color: var(--ct-muted); font-size: 12px; font-weight: 750; }
            .ct-metric-copy strong { margin-top: 2px; font-size: 27px; letter-spacing: -.03em; }
            .ct-metric-copy small { margin-top: 2px; overflow: hidden; color: var(--ct-muted); font-size: 11px; text-overflow: ellipsis; white-space: nowrap; }
            .ct-metric-arrow { margin-left: auto; align-self: flex-start; color: #94a3b8; }

            .ct-grid-main { display: grid; grid-template-columns: minmax(0, 1.6fr) minmax(290px, .8fr); gap: 18px; align-items: start; }
            .ct-stack { display: grid; gap: 18px; }
            .ct-panel { min-width: 0; overflow: hidden; border: 1px solid var(--ct-line); border-radius: 20px; background: #fff; box-shadow: 0 6px 18px rgba(15,23,42,.05); }

            .ct-panel-header {
                display: flex;
                align-items: flex-start;
                justify-content: space-between;
                gap: 16px;
                padding: 20px 21px 16px;
                border-bottom: 1px solid #edf2f7;
            }

            .ct-panel-heading { display: flex; gap: 12px; align-items: center; }
            .ct-panel-icon { display: grid; place-items: center; width: 39px; height: 39px; flex: 0 0 39px; border-radius: 12px; color: #1d4ed8; background: #eff6ff; }
            .ct-panel-icon.cyan { color: #0e7490; background: #ecfeff; }
            .ct-panel-icon.amber { color: #b45309; background: #fffbeb; }
            .ct-panel-icon.red { color: #b91c1c; background: #fef2f2; }
            .ct-panel-icon.green { color: #15803d; background: #f0fdf4; }
            .ct-panel-title small { display: block; margin-bottom: 3px; color: var(--ct-muted); font-size: 10px; font-weight: 800; letter-spacing: .1em; text-transform: uppercase; }
            .ct-panel-title h2 { margin: 0; font-size: 17px; letter-spacing: -.015em; }
            .ct-panel-link { color: #2563eb; font-size: 12px; font-weight: 800; white-space: nowrap; }
            button.ct-panel-link { padding: 0; border: 0; background: none; font: inherit; cursor: pointer; }
            .ct-panel-body { padding: 18px 21px 21px; }
            .ct-panel-body-flush { padding: 0; }

            .ct-list { display: grid; gap: 10px; }
            .ct-row-card { display: flex; align-items: center; gap: 13px; padding: 13px; border: 1px solid #e8edf4; border-radius: 14px; background: #fff; }
            .ct-row-card:hover { border-color: #cbd5e1; background: #fbfdff; }
            .ct-row-icon { display: grid; place-items: center; width: 39px; height: 39px; flex: 0 0 39px; border-radius: 12px; color: #0e7490; background: #ecfeff; }
            .ct-row-main { display: grid; min-width: 0; flex: 1; gap: 3px; }
            .ct-row-main strong { overflow: hidden; font-size: 13px; text-overflow: ellipsis; white-space: nowrap; }
            .ct-row-main span, .ct-row-main small { overflow: hidden; color: var(--ct-muted); font-size: 11px; line-height: 1.45; text-overflow: ellipsis; white-space: nowrap; }
            .ct-row-actions { display: flex; align-items: center; gap: 8px; flex: 0 0 auto; }

            .ct-delivery-list { display: grid; gap: 12px; }
            .ct-delivery { padding: 17px; border: 1px solid #e5eaf1; border-radius: 16px; background: linear-gradient(180deg, #fff, #fbfdff); }
            .ct-delivery-top { display: flex; align-items: flex-start; justify-content: space-between; gap: 15px; }
            .ct-delivery-title { min-width: 0; }
            .ct-delivery-title strong { display: block; font-size: 15px; }
            .ct-delivery-title span { display: block; margin-top: 4px; overflow: hidden; color: var(--ct-muted); font-size: 12px; text-overflow: ellipsis; white-space: nowrap; }
            .ct-delivery-details { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 10px; margin-top: 14px; }
            .ct-detail { min-width: 0; padding: 11px 12px; border-radius: 12px; background: var(--ct-soft); }
            .ct-detail span { display: block; color: var(--ct-muted); font-size: 10px; font-weight: 750; text-transform: uppercase; }
            .ct-detail strong { display: block; margin-top: 4px; overflow: hidden; font-size: 12px; text-overflow: ellipsis; white-space: nowrap; }
            .ct-delivery-actions { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 13px; }

            .ct-status { display: inline-flex; align-items: center; gap: 6px; width: fit-content; padding: 6px 9px; border-radius: 999px; color: #92400e; background: #fef3c7; font-size: 10px; font-weight: 850; white-space: nowrap; }
            .ct-status-in_progress, .ct-status-in_transit { color: #0e7490; background: #cffafe; }
            .ct-status-assigned, .ct-status-approved { color: #1d4ed8; background: #dbeafe; }
            .ct-status-active, .ct-status-completed, .ct-status-delivered { color: #15803d; background: #dcfce7; }
            .ct-status-cancelled, .ct-status-inactive { color: #b91c1c; background: #fee2e2; }

            .ct-role { display: inline-flex; align-items: center; gap: 6px; width: fit-content; padding: 6px 9px; border-radius: 999px; color: #475569; background: #f1f5f9; font-size: 10px; font-weight: 850; white-space: nowrap; }
            .ct-role-administrator { color: #1d4ed8; background: #dbeafe; }
            .ct-role-driver { color: #0e7490; background: #cffafe; }
            .ct-role-receiver { color: #7c3aed; background: #ede9fe; }

            .ct-condition { display: inline-flex; align-items: center; gap: 6px; width: fit-content; font-size: 11px; font-weight: 800; }
            .ct-condition-safe { color: #15803d; }
            .ct-condition-warning { color: #b45309; }
            .ct-condition-danger { color: #b91c1c; }
            .ct-condition-neutral { color: var(--ct-muted); }

            .ct-table-wrap { width: 100%; overflow-x: auto; }
            .ct-table { width: 100%; border-collapse: collapse; }
            .ct-table th { padding: 12px 15px; color: var(--ct-muted); background: #f8fafc; font-size: 10px; font-weight: 850; letter-spacing: .06em; text-align: left; text-transform: uppercase; }
            .ct-table td { padding: 13px 15px; border-top: 1px solid #edf2f7; color: var(--ct-copy); font-size: 12px; vertical-align: middle; }
            .ct-table td strong { display: block; color: var(--ct-ink); font-size: 12px; }
            .ct-table td small { display: block; margin-top: 3px; color: var(--ct-muted); font-size: 10px; }

            .ct-attention { display: grid; gap: 9px; }
            .ct-attention-item { display: flex; align-items: center; gap: 11px; padding: 12px; border-radius: 13px; background: #f8fafc; }
            .ct-attention-item > i { display: grid; place-items: center; width: 33px; height: 33px; flex: 0 0 33px; border-radius: 10px; color: #b45309; background: #fef3c7; }
            .ct-attention-item.danger > i { color: #b91c1c; background: #fee2e2; }
            .ct-attention-copy { display: grid; flex: 1; gap: 2px; }
            .ct-attention-copy strong { font-size: 12px; }
            .ct-attention-copy span { color: var(--ct-muted); font-size: 10px; line-height: 1.4; }
            .ct-attention-count { font-size: 19px; font-weight: 850; }

            .ct-vehicle { display: grid; gap: 11px; }
            .ct-vehicle-title { display: flex; align-items: center; gap: 12px; }
            .ct-vehicle-title > span { display: grid; place-items: center; width: 43px; height: 43px; border-radius: 13px; color: #0e7490; background: #ecfeff; font-size: 20px; }
            .ct-vehicle-title strong { display: block; font-size: 15px; }
            .ct-vehicle-title small { display: block; margin-top: 3px; color: var(--ct-muted); font-size: 11px; }
            .ct-vehicle-meta { display: grid; grid-template-columns: 1fr 1fr; gap: 9px; }

            .ct-alert { padding: 12px; border: 1px solid #fee2e2; border-radius: 13px; background: #fffafa; }
            .ct-alert strong { display: flex; justify-content: space-between; gap: 10px; color: #991b1b; font-size: 12px; }
            .ct-alert p { margin: 5px 0 0; color: #7f1d1d; font-size: 10px; line-height: 1.5; }

            .ct-empty { display: grid; place-items: center; min-height: 170px; padding: 24px; text-align: center; }
            .ct-empty > span { display: grid; place-items: center; width: 48px; height: 48px; margin-bottom: 10px; border-radius: 15px; color: #0e7490; background: #ecfeff; font-size: 21px; }
            .ct-empty strong { font-size: 13px; }
            .ct-empty p { max-width: 360px; margin: 6px 0 0; color: var(--ct-muted); font-size: 11px; line-height: 1.55; }
            .ct-empty .ct-button { margin-top: 13px; }

            @media (max-width: 1100px) {
                .ct-metrics { grid-template-columns: repeat(2, minmax(0, 1fr)); }
                .ct-grid-main { grid-template-columns: 1fr; }
            }

            @media (max-width: 760px) {
                .ct-hero { align-items: flex-start; flex-direction: column; padding: 25px 22px; }
                .ct-actions { justify-content: flex-start; width: 100%; }
                .ct-delivery-details { grid-template-columns: 1fr 1fr; }
                .ct-panel-header { align-items: center; }
            }

            @media (max-width: 520px) {
                .ct-dashboard { gap: 14px; }
                .ct-hero { border-radius: 19px; }
                .ct-hero h1 { font-size: 28px; }
                .ct-actions .ct-button { width: 100%; }
                .ct-metrics { grid-template-columns: 1fr 1fr; gap: 9px; }
                .ct-metric { align-items: flex-start; flex-direction: column; gap: 10px; padding: 14px; }
                .ct-metric-icon { width: 38px; height: 38px; flex-basis: 38px; }
                .ct-metric-copy strong { font-size: 23px; }
                .ct-metric-arrow { position: absolute; top: 12px; right: 12px; }
                .ct-panel { border-radius: 17px; }
                .ct-panel-header, .ct-panel-body { padding-left: 15px; padding-right: 15px; }
                .ct-delivery-details { grid-template-columns: 1fr; }
                .ct-row-card { align-items: flex-start; }
                .ct-row-actions { align-items: stretch; flex-direction: column; }
                .ct-vehicle-meta { grid-template-columns: 1fr; }
            }
        </style>
    @endpush
@endonce
