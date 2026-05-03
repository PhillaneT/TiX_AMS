@extends('layouts.app')

@section('content')
<div class="max-w-3xl mx-auto px-6 py-8">

    <div class="mb-6">
        <h1 class="text-2xl font-semibold text-navy-900">My Signature &amp; Stamp</h1>
        <p class="text-sm text-gray-600 mt-1">
            Saved here once and reused on every Assessor Declaration, Marking Report
            and POE submission you produce. You can update them at any time.
        </p>
    </div>

    @if(session('status'))
        <div class="mb-4 rounded-lg bg-green-50 border border-green-200 text-green-800 text-sm px-4 py-3">
            {{ session('status') }}
        </div>
    @endif

    @if($errors->any())
        <div class="mb-4 rounded-lg bg-red-50 border border-red-200 text-red-800 text-sm px-4 py-3">
            <ul class="list-disc pl-5">
                @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('profile.update') }}" enctype="multipart/form-data" class="space-y-8">
        @csrf
        @method('PUT')

        {{-- ───────── ETQA registration ───────── --}}
        <section class="bg-white rounded-xl border border-gray-200 p-5">
            <h2 class="text-base font-semibold text-navy-900 mb-3">ETQA registration number</h2>
            <input type="text" name="etqa_registration"
                   value="{{ old('etqa_registration', $user->etqa_registration) }}"
                   placeholder="e.g. 1122334"
                   class="w-full max-w-sm rounded-lg border-gray-300 focus:border-[#e3b64d] focus:ring-[#e3b64d] text-sm">
            <p class="text-xs text-gray-500 mt-2">
                Pre-filled into the Declaration page on every signed-off submission.
            </p>
        </section>

        {{-- ───────── Signature ───────── --}}
        <section class="bg-white rounded-xl border border-gray-200 p-5">
            <h2 class="text-base font-semibold text-navy-900">Signature</h2>
            <p class="text-xs text-gray-500 mt-1 mb-3">
                Draw with your mouse / finger, or upload a PNG with a transparent background.
            </p>

            @if($user->signature_path)
                <div class="mb-3 flex items-center gap-4">
                    <img src="{{ route('profile.asset', 'signature') }}?v={{ $user->updated_at?->timestamp }}"
                         alt="Current signature"
                         class="h-20 max-w-xs object-contain bg-gray-50 border border-dashed border-gray-300 rounded p-1">
                    <label class="inline-flex items-center gap-2 text-sm text-red-600">
                        <input type="checkbox" name="remove_signature" value="1" class="rounded border-gray-300">
                        Remove current signature
                    </label>
                </div>
            @endif

            <div class="border border-dashed border-gray-300 rounded-lg bg-gray-50 inline-block">
                <canvas id="sig-pad" width="500" height="160"
                        class="block bg-white rounded-lg cursor-crosshair touch-none"></canvas>
            </div>
            <div class="mt-2 flex gap-2">
                <button type="button" id="sig-clear"
                        class="px-3 py-1.5 text-xs rounded-md bg-gray-100 hover:bg-gray-200 text-gray-700">
                    Clear pad
                </button>
                <span class="text-xs text-gray-500 self-center">
                    Drawing on the pad replaces any existing signature.
                </span>
            </div>
            <input type="hidden" name="signature_image" id="signature_image">

            <div class="mt-4 pt-4 border-t border-gray-100">
                <label class="block text-sm text-gray-700 mb-1">Or upload an image file</label>
                <input type="file" name="signature_file" accept="image/png,image/jpeg"
                       class="text-sm">
            </div>
        </section>

        {{-- ───────── Generated rubber stamp ───────── --}}
        <section class="bg-white rounded-xl border border-gray-200 p-5">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h2 class="text-base font-semibold text-navy-900">Official stamp</h2>
                    <p class="text-xs text-gray-500 mt-1">
                        Use the built-in old-school rubber stamp (your details below stay static,
                        the date is stamped fresh on every PDF), or upload your own image.
                    </p>
                </div>
                <label class="inline-flex items-center gap-2 text-sm text-gray-700 mt-1 shrink-0">
                    <input type="checkbox" name="stamp_use_generated" value="1"
                           class="rounded border-gray-300 text-[#e3b64d] focus:ring-[#e3b64d]"
                           {{ $user->stamp_use_generated ? 'checked' : '' }}>
                    Use generated stamp
                </label>
            </div>

            <div class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-6">
                {{-- Editor --}}
                <div class="space-y-3">
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">Top arc text (organisation)</label>
                        <input type="text" name="stamp_org_top" maxlength="60"
                               value="{{ old('stamp_org_top', $user->stamp_org_top) }}"
                               placeholder="AJANANOVA ASSESSMENT CENTRE"
                               class="w-full text-sm rounded border-gray-300 focus:border-[#e3b64d] focus:ring-[#e3b64d]">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">Bottom arc text (accreditation)</label>
                        <input type="text" name="stamp_org_bottom" maxlength="60"
                               value="{{ old('stamp_org_bottom', $user->stamp_org_bottom) }}"
                               placeholder="MICT SETA ACCREDITED"
                               class="w-full text-sm rounded border-gray-300 focus:border-[#e3b64d] focus:ring-[#e3b64d]">
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1">Role label</label>
                            <input type="text" name="stamp_role" maxlength="40"
                                   value="{{ old('stamp_role', $user->stamp_role ?: 'ASSESSOR') }}"
                                   class="w-full text-sm rounded border-gray-300 focus:border-[#e3b64d] focus:ring-[#e3b64d]">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1">Holder name</label>
                            <input type="text" name="stamp_holder_name" maxlength="60"
                                   value="{{ old('stamp_holder_name', $user->stamp_holder_name ?: $user->name) }}"
                                   class="w-full text-sm rounded border-gray-300 focus:border-[#e3b64d] focus:ring-[#e3b64d]">
                        </div>
                    </div>
                    <p class="text-[11px] text-gray-500 leading-snug">
                        ETQA number (above) is also stamped automatically. The date refreshes on every PDF.
                    </p>
                </div>

                {{-- Live preview --}}
                <div class="flex items-center justify-center">
                    <svg id="stamp-preview" viewBox="0 0 200 200" class="w-48 h-48"
                         xmlns="http://www.w3.org/2000/svg" aria-label="Stamp preview"></svg>
                </div>
            </div>

            <div class="mt-6 pt-5 border-t border-gray-100">
                <h3 class="text-sm font-semibold text-navy-900 mb-1">Or upload an image stamp</h3>
                <p class="text-xs text-gray-500 mb-3">
                    Used when "Use generated stamp" is off. Square PNG with transparent background works best.
                </p>

                @if($user->stamp_path)
                    <div class="mb-3 flex items-center gap-4">
                        <img src="{{ route('profile.asset', 'stamp') }}?v={{ $user->updated_at?->timestamp }}"
                             alt="Current stamp"
                             class="h-24 w-24 object-contain bg-gray-50 border border-dashed border-gray-300 rounded p-1">
                        <label class="inline-flex items-center gap-2 text-sm text-red-600">
                            <input type="checkbox" name="remove_stamp" value="1" class="rounded border-gray-300">
                            Remove uploaded stamp
                        </label>
                    </div>
                @endif

                <input type="file" name="stamp_file" accept="image/png,image/jpeg" class="text-sm">
            </div>
        </section>

        <div class="flex gap-3">
            <button type="submit"
                    class="px-5 py-2 rounded-lg bg-[#e3b64d] hover:bg-[#cfa23e] text-white font-medium text-sm">
                Save profile
            </button>
            <a href="{{ route('dashboard') }}"
               class="px-5 py-2 rounded-lg bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm">
                Cancel
            </a>
        </div>
    </form>
</div>

<script>
(() => {
    const canvas = document.getElementById('sig-pad');
    const ctx    = canvas.getContext('2d');
    const hidden = document.getElementById('signature_image');
    let drawing  = false, dirty = false, lastX = 0, lastY = 0;

    ctx.fillStyle   = '#ffffff';
    ctx.fillRect(0, 0, canvas.width, canvas.height);
    ctx.lineWidth   = 2.2;
    ctx.lineCap     = 'round';
    ctx.lineJoin    = 'round';
    ctx.strokeStyle = '#0c1a36';

    const pos = (e) => {
        const r = canvas.getBoundingClientRect();
        const t = e.touches ? e.touches[0] : e;
        return { x: (t.clientX - r.left) * (canvas.width / r.width),
                 y: (t.clientY - r.top)  * (canvas.height / r.height) };
    };
    const start = (e) => { e.preventDefault(); drawing = true; dirty = true;
                           const p = pos(e); lastX = p.x; lastY = p.y; };
    const move  = (e) => { if (!drawing) return; e.preventDefault();
                           const p = pos(e);
                           ctx.beginPath(); ctx.moveTo(lastX, lastY); ctx.lineTo(p.x, p.y); ctx.stroke();
                           lastX = p.x; lastY = p.y; };
    const end   = ()  => { drawing = false; if (dirty) hidden.value = canvas.toDataURL('image/png'); };

    canvas.addEventListener('mousedown',  start);
    canvas.addEventListener('mousemove',  move);
    window.addEventListener('mouseup',    end);
    canvas.addEventListener('touchstart', start, { passive: false });
    canvas.addEventListener('touchmove',  move,  { passive: false });
    canvas.addEventListener('touchend',   end);

    document.getElementById('sig-clear').addEventListener('click', () => {
        ctx.fillStyle = '#ffffff';
        ctx.fillRect(0, 0, canvas.width, canvas.height);
        hidden.value = '';
        dirty = false;
    });
})();

/* ─── Live preview of the generated rubber stamp (SVG) ─────────────────── */
(() => {
    const svg = document.getElementById('stamp-preview');
    if (!svg) return;
    const NS = 'http://www.w3.org/2000/svg';
    const RED = '#a81d1d';

    const inputs = {
        top:    document.querySelector('[name="stamp_org_top"]'),
        bot:    document.querySelector('[name="stamp_org_bottom"]'),
        role:   document.querySelector('[name="stamp_role"]'),
        name:   document.querySelector('[name="stamp_holder_name"]'),
        etqa:   document.querySelector('[name="etqa_registration"]'),
    };

    const today = new Date().toLocaleDateString('en-GB', {
        day: '2-digit', month: 'short', year: 'numeric'
    }).toUpperCase();

    const render = () => {
        while (svg.firstChild) svg.removeChild(svg.firstChild);

        const cx = 100, cy = 100, R = 92, r = 82;
        const tilt = -7;
        const g = document.createElementNS(NS, 'g');
        g.setAttribute('transform', `rotate(${tilt} ${cx} ${cy})`);
        g.setAttribute('stroke', RED);
        g.setAttribute('fill', 'none');

        // Two concentric rings
        for (const rad of [R, r]) {
            const c = document.createElementNS(NS, 'circle');
            c.setAttribute('cx', cx); c.setAttribute('cy', cy);
            c.setAttribute('r', rad); c.setAttribute('stroke-width', rad === R ? 2.4 : 1.4);
            g.appendChild(c);
        }

        // Curved top + bottom text via textPath
        const arcR = (R + r) / 2;
        const topId = 'arc-top-' + Math.random().toString(36).slice(2);
        const botId = 'arc-bot-' + Math.random().toString(36).slice(2);
        const defs = document.createElementNS(NS, 'defs');
        // Top arc: left-to-right across the top
        const topPath = document.createElementNS(NS, 'path');
        topPath.setAttribute('id', topId);
        topPath.setAttribute('d', `M ${cx - arcR},${cy} A ${arcR},${arcR} 0 0 1 ${cx + arcR},${cy}`);
        defs.appendChild(topPath);
        // Bottom arc: left-to-right across the bottom (so text reads upright)
        const botPath = document.createElementNS(NS, 'path');
        botPath.setAttribute('id', botId);
        botPath.setAttribute('d', `M ${cx - arcR},${cy} A ${arcR},${arcR} 0 1 0 ${cx + arcR},${cy}`);
        defs.appendChild(botPath);
        g.appendChild(defs);

        const arcText = (id, txt, dyOff) => {
            const t = document.createElementNS(NS, 'text');
            t.setAttribute('fill', RED); t.setAttribute('stroke', 'none');
            t.setAttribute('font-family', 'Georgia, serif');
            t.setAttribute('font-weight', '700');
            t.setAttribute('font-size', '13');
            t.setAttribute('letter-spacing', '1.5');
            const tp = document.createElementNS(NS, 'textPath');
            tp.setAttributeNS('http://www.w3.org/1999/xlink', 'href', '#' + id);
            tp.setAttribute('startOffset', '50%');
            tp.setAttribute('text-anchor', 'middle');
            tp.setAttribute('side', dyOff > 0 ? 'right' : 'left');
            tp.textContent = (txt || '').toUpperCase();
            t.appendChild(tp);
            g.appendChild(t);
        };
        arcText(topId, inputs.top.value || 'AJANANOVA ASSESSMENT CENTRE', -1);
        arcText(botId, inputs.bot.value || 'MICT SETA ACCREDITED',         +1);

        // Centre block
        const lines = [
            { t: (inputs.role.value || 'ASSESSOR').toUpperCase(),       y: cy - 22, sz: 9,  w: 600 },
            { t: today,                                                  y: cy - 4,  sz: 17, w: 800 },
            { t: inputs.name.value || 'Holder Name',                     y: cy + 14, sz: 11, w: 600 },
            { t: inputs.etqa.value ? 'ETQA: ' + inputs.etqa.value : '',  y: cy + 26, sz: 9,  w: 500 },
        ];
        // Divider lines around the date
        for (const yLine of [cy - 12, cy + 4]) {
            const ln = document.createElementNS(NS, 'line');
            ln.setAttribute('x1', cx - 38); ln.setAttribute('x2', cx + 38);
            ln.setAttribute('y1', yLine);   ln.setAttribute('y2', yLine);
            ln.setAttribute('stroke-width', 0.8);
            g.appendChild(ln);
        }
        for (const l of lines) {
            if (!l.t) continue;
            const t = document.createElementNS(NS, 'text');
            t.setAttribute('x', cx); t.setAttribute('y', l.y);
            t.setAttribute('text-anchor', 'middle');
            t.setAttribute('fill', RED); t.setAttribute('stroke', 'none');
            t.setAttribute('font-family', 'Georgia, serif');
            t.setAttribute('font-size', l.sz);
            t.setAttribute('font-weight', l.w);
            t.setAttribute('letter-spacing', l.sz >= 15 ? 2 : 0.6);
            t.textContent = l.t;
            g.appendChild(t);
        }

        svg.appendChild(g);
    };

    Object.values(inputs).forEach(el => el && el.addEventListener('input', render));
    render();
})();
</script>
@endsection
