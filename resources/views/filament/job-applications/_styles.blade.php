{{-- Gaya khusus halaman pelamar. Memakai variabel warna Filament; tanpa tema khusus. --}}
@once
    <style>
        .pa-muted { color: var(--gray-500); }
        .pa-answers { margin: 0; }
        .pa-q { padding: 1rem 0; border-bottom: 1px solid var(--gray-200); }
        .dark .pa-q { border-color: var(--gray-800); }
        .pa-q:first-child { padding-top: 0; }
        .pa-q:last-child { border-bottom: 0; padding-bottom: 0; }
        .pa-q dt { font-size: .8125rem; color: var(--gray-500); }
        .pa-q dd { margin: .375rem 0 0; line-height: 1.65; font-size: .9375rem; color: var(--gray-950); }
        .dark .pa-q dd { color: #fff; }
        .pa-head { display: inline-flex; align-items: center; gap: 1rem; }
        .pa-avatar { width: 4rem; height: 4rem; border-radius: 999px; display: inline-flex; align-items: center; justify-content: center; background: var(--primary-600); color: #fff; font-size: 1.375rem; font-weight: 700; letter-spacing: 0; flex: none; }
        .pa-sub { display: inline-flex; align-items: center; gap: .625rem; flex-wrap: wrap; }
        .pa-pipeline { display: flex; align-items: flex-start; padding: .25rem 1rem .25rem; }
        .pa-step { flex: 1; display: flex; flex-direction: column; align-items: center; gap: .5rem; position: relative; font-size: .8125rem; font-weight: 600; color: var(--gray-500); text-align: center; }
        .pa-step i { width: 2rem; height: 2rem; border-radius: 999px; display: flex; font-size: .8125rem; align-items: center; justify-content: center; font-style: normal; background: #fff; border: 2px solid var(--gray-300); position: relative; z-index: 1; }
        .dark .pa-step i { background: var(--gray-900); border-color: var(--gray-600); }
        .pa-step:not(:last-child)::after { content: ''; position: absolute; top: .9375rem; left: 50%; width: 100%; height: 2px; background: var(--gray-300); }
        .pa-step.is-done i { background: var(--primary-600); border-color: var(--primary-600); color: #fff; }
        .pa-step.is-done:not(:last-child)::after { background: var(--primary-600); }
        .pa-step.is-now i { border-color: var(--primary-600); color: var(--primary-600); box-shadow: 0 0 0 4px color-mix(in srgb, var(--primary-600) 18%, transparent); }
        .pa-step.is-done, .pa-step.is-now { color: var(--gray-950); }
        .dark .pa-step.is-done, .dark .pa-step.is-now { color: #fff; }
        .pa-step.is-bad i { background: #b91c1c; border-color: #b91c1c; color: #fff; box-shadow: none; }
        .pa-docs { display: grid; gap: 1.5rem; grid-template-columns: minmax(0, 1fr); }
        @media (min-width: 1024px) { .pa-docs { grid-template-columns: 18rem minmax(0, 1fr); } }
        .pa-doclist { display: flex; flex-direction: column; gap: .75rem; }
        .pa-doc { display: flex; align-items: center; gap: .75rem; width: 100%; text-align: left; padding: .75rem; border-radius: .625rem; border: 1px solid var(--gray-200); background: #fff; cursor: pointer; }
        .dark .pa-doc { background: var(--gray-900); border-color: var(--gray-700); }
        .pa-doc.is-on { border-color: var(--primary-600); box-shadow: 0 0 0 3px color-mix(in srgb, var(--primary-600) 18%, transparent); }
        .pa-doc-ic { width: 2.5rem; height: 2.5rem; flex: none; border-radius: .5rem; display: flex; align-items: center; justify-content: center; background: var(--gray-100); font-size: .6875rem; font-weight: 700; color: var(--gray-600); }
        .dark .pa-doc-ic { background: var(--gray-800); color: var(--gray-300); }
        .pa-doc strong { display: block; font-size: .875rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 11.5rem; }
        .pa-doc small { color: var(--gray-500); font-size: .75rem; }
        .pa-note { display: flex; gap: .5rem; padding: .875rem; border-radius: .625rem; background: var(--gray-100); color: var(--gray-700); font-size: .8125rem; line-height: 1.5; }
        .dark .pa-note { background: var(--gray-800); color: var(--gray-300); }
        .pa-viewer { border: 1px solid var(--gray-200); border-radius: .75rem; overflow: hidden; background: #fff; }
        .dark .pa-viewer { border-color: var(--gray-700); background: var(--gray-900); }
        .pa-toolbar { display: flex; align-items: center; gap: .75rem; flex-wrap: wrap; padding: .625rem .875rem; border-bottom: 1px solid var(--gray-200); font-size: .875rem; }
        .dark .pa-toolbar { border-color: var(--gray-700); }
        .pa-toolbar .grow { flex: 1; min-width: 0; }
        .pa-toolbar a { font-weight: 600; color: var(--primary-600); }
        .pa-toolbar a.quiet { font-weight: 500; color: var(--gray-500); }
        .pa-frame { display: block; width: 100%; height: 78vh; min-height: 32rem; border: 0; background: var(--gray-400); }
        .pa-stage { background: var(--gray-400); display: flex; justify-content: center; padding: 1.5rem; max-height: 78vh; overflow: auto; }
        .pa-stage img { max-width: 100%; height: auto; background: #fff; box-shadow: 0 1px 3px rgba(0,0,0,.25), 0 8px 24px rgba(0,0,0,.15); }
        .pa-empty { padding: 3rem 1.5rem; text-align: center; color: var(--gray-500); }
        .pa-tl { list-style: none; margin: 0; padding: 0; }
        .pa-tl li { position: relative; padding: 0 0 1.375rem 1.75rem; }
        .pa-tl li::before { content: ''; position: absolute; left: .4375rem; top: 1.125rem; bottom: -.25rem; width: 2px; background: var(--gray-200); }
        .dark .pa-tl li::before { background: var(--gray-700); }
        .pa-tl li:last-child::before { display: none; }
        .pa-tl .dot { position: absolute; left: 0; top: .1875rem; width: 1rem; height: 1rem; border-radius: 999px; background: #fff; border: 2px solid var(--gray-400); }
        .dark .pa-tl .dot { background: var(--gray-900); }
        .pa-tl .dot.is-status { background: var(--primary-600); border-color: var(--primary-600); }
        .pa-tl .meta { color: var(--gray-500); font-size: .75rem; margin-top: .125rem; }
        .pa-tl .note { margin-top: .375rem; padding: .625rem .75rem; border-radius: .5rem; background: var(--gray-100); white-space: pre-line; }
        .dark .pa-tl .note { background: var(--gray-800); }
    </style>
@endonce
