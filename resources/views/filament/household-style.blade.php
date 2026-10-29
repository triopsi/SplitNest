<style>
.hh-stack{display:grid;gap:1.5rem}.hh-hero{padding:2rem;border-radius:1.25rem;background:linear-gradient(115deg,#113c43,#176e72);color:white;display:flex;align-items:center;justify-content:space-between;gap:1.5rem;flex-wrap:wrap}
.hh-hero h2{font-size:1.7rem;font-weight:700;letter-spacing:-.03em}.hh-hero p{color:#c9e5e3;max-width:46rem;margin-top:.5rem}.hh-kicker{font-size:.75rem;letter-spacing:.14em;text-transform:uppercase;color:#9cdbcf;margin-bottom:.65rem}
.hh-picker{display:flex;gap:.75rem;flex-wrap:wrap}.hh-picker label{color:#c9e5e3;font-size:.85rem}.hh-hero .hh-link{color:#c9e5e3}
.hh-control{display:flex;gap:1rem;align-items:center;flex-wrap:wrap}.hh-input{padding:.65rem .9rem;border:1px solid #b8c9ca;border-radius:.65rem;background:white;color:#17353b;font:inherit}
.hh-cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:1rem}.hh-card,.hh-section{border:1px solid #dbe5e4;background:white;border-radius:1rem;overflow:hidden}.hh-card{padding:1.4rem}.hh-card label{color:#576b70;font-size:.85rem}.hh-card-head{display:flex;align-items:center;gap:.5rem}.hh-icon{width:1.1rem;height:1.1rem;color:#0c8276;flex-shrink:0}.hh-number{font-size:1.75rem;font-weight:700;letter-spacing:-.04em;margin-top:.4rem;color:#173e42}
.hh-section-head{padding:1.25rem 1.5rem;border-bottom:1px solid #dbe5e4;display:flex;align-items:center;justify-content:space-between;gap:1rem}.hh-section-head h3{font-size:1.05rem;font-weight:650}
.hh-scroll{overflow:auto}.hh-table{border-collapse:collapse;width:100%;font-size:.9rem;white-space:nowrap}.hh-table th{background:#f3f7f6;color:#496267;font-size:.75rem;letter-spacing:.035em;text-align:left}.hh-table th,.hh-table td{padding:.9rem 1.25rem;border:1px solid #e1e9e8}.hh-table th:first-child,.hh-table td:first-child{border-left:0}.hh-table th:last-child,.hh-table td:last-child{border-right:0}.hh-table .num{text-align:right;font-variant-numeric:tabular-nums}
.hh-transfer{padding:1.2rem 1.5rem;display:flex;align-items:center;justify-content:space-between;gap:1rem;border-bottom:1px solid #e1e9e8}.hh-transfer:last-child{border-bottom:0}.hh-transfer strong{color:#147868;font-size:1.2rem}.hh-muted{color:#697c80;font-size:.85rem}.hh-note{padding:1.25rem 1.5rem;color:#5b7074;font-size:.9rem}.hh-warning{padding:1rem 1.25rem;border-radius:.75rem;background:#fff3d8;color:#815512}.hh-badge{font-size:.75rem;padding:.3rem .6rem;border-radius:99px;background:#e1f2e9;color:#17644c}.hh-link{color:#0c8276;text-decoration:underline;text-underline-offset:3px}.hh-negative{color:#bb3946!important}
.hh-footer{text-align:center;color:#697c80;font-size:.8rem;padding:.75rem}.dark .hh-footer{color:#a4bcb7}
.hh-person{display:inline-flex;align-items:center;gap:.45rem}.hh-avatar{width:1.5rem;height:1.5rem;border-radius:99px;object-fit:cover}.hh-hero .hh-avatar{width:2.2rem;height:2.2rem}
.hh-person-line{display:flex;align-items:center;gap:.45rem;flex-wrap:wrap}
.hh-form{padding:0 1.5rem}.hh-form .fi-sc-actions{padding:0}
.dark .hh-card,.dark .hh-section{background:#182728;border-color:#34494b}.dark .hh-number{color:#d8f0ec}.dark .hh-table th{background:#213738;color:#c0d9d5}.dark .hh-table th,.dark .hh-table td,.dark .hh-section-head,.dark .hh-transfer{border-color:#34494b}.dark .hh-card label,.dark .hh-muted,.dark .hh-note{color:#a4bcb7}.dark .hh-icon{color:#66dac4}
.dark .hh-transfer strong,.dark .hh-link{color:#66dac4}.dark .hh-negative{color:#ff98a1!important}
.sn-settings-tabs{border-radius:.85rem;overflow:hidden}
.sn-settings-tabs > .fi-tabs{padding:.65rem .75rem}
.sn-settings-tabs .fi-tabs-item{min-height:2.25rem}
.dark .sn-settings-tabs{background:#18181b}
.dark .sn-settings-tabs > .fi-tabs{border-color:#303034}
.dark .sn-settings-tabs .fi-tabs-item.fi-active{background:#27272a}
.sn-profile-tabs .fi-sc-form{gap:1.5rem}
.fi-simple-main:has(.sn-profile-tabs){background:transparent;box-shadow:none;--tw-ring-shadow:0 0 transparent;padding:0;margin:0}
.fi-simple-main-ctn:has(.sn-profile-tabs){align-items:flex-start;justify-content:center;padding:6rem 1.5rem 3rem}
.fi-simple-page:has(.sn-profile-tabs) .fi-simple-header{align-items:flex-start;text-align:start}
@media(max-width:639px){.fi-simple-main-ctn:has(.sn-profile-tabs){padding-top:4rem}.sn-settings-tabs .fi-sc-tabs-tab.fi-active{padding:1rem}}

.hh-payments{display:grid;gap:1rem;min-width:0}
.hh-payment-list{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,28rem),1fr));gap:1rem;align-items:stretch}
.hh-payment-card{display:flex;flex-direction:column;min-width:0;border:1px solid #dbe5e4;border-top:3px solid #0d9488;border-radius:.85rem;background:#fff;overflow:hidden}
.hh-payment-head{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1.5rem;padding:1.25rem}
.hh-payment-route{display:flex;align-items:center;gap:1rem;min-width:0;flex:1 1 16rem}
.hh-payment-person{display:grid;gap:.4rem;min-width:0;flex:1}.hh-payment-person .hh-person{font-size:.95rem;font-weight:600;overflow-wrap:anywhere}.hh-payment-person .hh-avatar{flex-shrink:0}
.hh-payment-label{display:block;font-size:.75rem;font-weight:500;line-height:1.5;color:#697c80}
.hh-payment-arrow{color:#0d9488;font-size:1.5rem;flex-shrink:0}
.hh-payment-sign{display:inline-block;flex-shrink:0;width:1rem;color:#0d9488;font-weight:700}.hh-payment-equation-total{border-top:2px solid #dbe5e4;padding-top:.9rem;margin-top:auto;align-items:center}.hh-payment-equation-total dt{font-weight:650}.hh-payment-equation-total strong{font-size:1.6rem;font-weight:750;line-height:1.2;letter-spacing:-.035em;color:#0f766e}
.hh-payment-breakdown{display:flex;flex-direction:column;flex:1;padding:1rem 1.25rem;border-top:1px solid #e1e9e8}.hh-payment-breakdown dl{display:flex;flex-direction:column;flex:1;gap:.6rem;margin-top:.65rem}.hh-payment-breakdown dl>div{display:flex;justify-content:space-between;gap:1.25rem;font-size:.85rem}.hh-payment-breakdown dt{display:flex;gap:.4rem;min-width:0;overflow-wrap:anywhere}.hh-payment-breakdown dd{white-space:nowrap;font-variant-numeric:tabular-nums}
.hh-payment-reference{display:grid;gap:.25rem;padding:.85rem 1.25rem;background:#f3f7f6;font-size:.85rem;overflow-wrap:anywhere}
.hh-payment-help{font-size:.8rem;color:#697c80}.hh-payment-help summary{cursor:pointer;width:fit-content;border-radius:.25rem}.hh-payment-help summary:focus-visible{outline:2px solid #0d9488;outline-offset:4px}.hh-payment-help p{margin-top:.65rem;line-height:1.6}
.hh-payment-empty{display:flex;align-items:center;gap:.75rem;padding:1.25rem;border:1px dashed #b8c9ca;border-radius:.85rem;font-size:.9rem}
.dark .hh-payment-card{background:#1c1c1f;border-color:#36383b;border-top-color:#2dd4bf}.dark .hh-payment-breakdown{border-color:#36383b}.dark .hh-payment-reference{background:#232729}.dark .hh-payment-label,.dark .hh-payment-help{color:#a4bcb7}.dark .hh-payment-equation-total{border-color:#36383b}.dark .hh-payment-equation-total strong,.dark .hh-payment-sign,.dark .hh-payment-arrow{color:#5eead4}.dark .hh-payment-empty{border-color:#34494b}

.hh-cost-share{display:flex;flex-direction:column;gap:.3rem;min-width:12rem;white-space:normal;font-variant-numeric:tabular-nums}.hh-cost-share>strong{font-size:1.1rem;color:#0f766e}.hh-cost-funded,.hh-cost-assigned,.hh-cost-funding>div{display:flex;justify-content:space-between;gap:1rem;font-size:.8rem}.hh-cost-funded{margin-top:.4rem;color:#697c80}.hh-cost-assigned{border-top:1px solid #dbe5e4;padding-top:.4rem}.hh-cost-funding{display:grid;gap:.5rem;min-width:11rem;font-variant-numeric:tabular-nums}.hh-cost-funding>div{color:#697c80}.hh-cost-funding .hh-cost-remainder{color:#0f766e;border-top:1px solid #dbe5e4;padding-top:.5rem}.dark .hh-cost-share>strong,.dark .hh-cost-funding .hh-cost-remainder{color:#5eead4}.dark .hh-cost-funded,.dark .hh-cost-funding>div{color:#a4bcb7}.dark .hh-cost-assigned,.dark .hh-cost-funding .hh-cost-remainder{border-color:#36383b}
</style>
