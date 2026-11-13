@once
    <style>
        .dx-muted { color: var(--gray-500); }
        .dx-att { display: grid; gap: 1rem; grid-template-columns: repeat(2, minmax(0, 1fr)); }
        @media (min-width: 1024px) { .dx-att { grid-template-columns: repeat(4, minmax(0, 1fr)); } }
        .dx-att a { display: flex; align-items: center; gap: .875rem; padding: 1rem; border-radius: .875rem; background: #fff; border: 1px solid var(--gray-200); box-shadow: 0 1px 2px rgba(0,0,0,.04); transition: box-shadow .15s, transform .15s; }
        .dark .dx-att a { background: var(--gray-900); border-color: var(--gray-800); }
        .dx-att a:hover { box-shadow: 0 8px 20px rgba(15,23,42,.1); transform: translateY(-1px); }
        .dx-ico { width: 2.75rem; height: 2.75rem; flex: none; border-radius: .75rem; display: flex; align-items: center; justify-content: center; }
        .dx-ico svg { width: 1.375rem; height: 1.375rem; }
        .dx-c-indigo { background: #eef2ff; color: #4338ca; } .dx-c-blue { background: #eff6ff; color: #1d4ed8; }
        .dx-c-amber { background: #fffbeb; color: #b45309; } .dx-c-red { background: #fef2f2; color: #b91c1c; } .dx-c-slate { background: var(--gray-100); color: var(--gray-600); }
        .dark .dx-c-indigo { background: rgba(99,102,241,.18); color: #a5b4fc; } .dark .dx-c-blue { background: rgba(59,130,246,.18); color: #93c5fd; }
        .dark .dx-c-amber { background: rgba(245,158,11,.18); color: #fcd34d; } .dark .dx-c-red { background: rgba(239,68,68,.18); color: #fca5a5; }
        .dx-att b { display: block; font-size: 1.5rem; letter-spacing: -.02em; line-height: 1.1; color: var(--gray-950); }
        .dark .dx-att b { color: #fff; }
        .dx-att .t { font-size: .8125rem; color: var(--gray-600); }
        .dark .dx-att .t { color: var(--gray-400); }
        .dx-att .go { margin-left: auto; color: var(--gray-400); }
        .dx-att .go svg { width: 1.125rem; height: 1.125rem; }
        .dx-seg { display: flex; height: .875rem; border-radius: .4375rem; overflow: hidden; gap: 2px; }
        .dx-leg { display: grid; gap: .625rem 1rem; margin-top: 1rem; grid-template-columns: repeat(2, minmax(0, 1fr)); }
        @media (min-width: 640px) { .dx-leg { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
        .dx-leg div { display: flex; align-items: center; gap: .5rem; font-size: .8125rem; }
        .dx-leg i { width: .625rem; height: .625rem; border-radius: .1875rem; flex: none; }
        .dx-leg b { margin-left: auto; font-weight: 600; }
        .dx-qa { display: grid; gap: .625rem; grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .dx-qa a { display: flex; flex-direction: column; gap: .625rem; padding: .875rem; border: 1px solid var(--gray-200); border-radius: .75rem; font-weight: 600; font-size: .875rem; }
        .dark .dx-qa a { border-color: var(--gray-700); }
        .dx-qa a:hover { background: var(--gray-50); border-color: var(--gray-300); }
        .dark .dx-qa a:hover { background: var(--gray-800); }
        .dx-qa .dx-ico { width: 2.125rem; height: 2.125rem; border-radius: .5625rem; background: var(--gray-100); color: var(--gray-700); }
        .dark .dx-qa .dx-ico { background: var(--gray-800); color: var(--gray-300); }
        .dx-qa .dx-ico svg { width: 1.125rem; height: 1.125rem; }
        .dx-dl { display: flex; align-items: center; gap: .75rem; padding: .75rem 0; border-bottom: 1px solid var(--gray-100); }
        .dark .dx-dl { border-color: var(--gray-800); }
        .dx-dl:last-child { border-bottom: 0; padding-bottom: 0; }
        .dx-dl:first-child { padding-top: 0; }
        .dx-pill { display: inline-flex; align-items: center; gap: .375rem; height: 1.5rem; padding: 0 .625rem; border-radius: 999px; font-size: .75rem; font-weight: 600; white-space: nowrap; }
        .dx-pill svg { width: .875rem; height: .875rem; }
        .dx-p-red { background: #fef2f2; color: #b91c1c; } .dx-p-amber { background: #fffbeb; color: #b45309; } .dx-p-slate { background: var(--gray-100); color: var(--gray-700); }
        .dark .dx-p-red { background: rgba(239,68,68,.18); color: #fca5a5; } .dark .dx-p-amber { background: rgba(245,158,11,.18); color: #fcd34d; } .dark .dx-p-slate { background: var(--gray-800); color: var(--gray-300); }
    </style>
@endonce
