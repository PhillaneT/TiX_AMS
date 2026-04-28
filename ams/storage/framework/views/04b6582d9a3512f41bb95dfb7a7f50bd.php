<?php $__env->startSection('title', $assignment->name . ' — ' . $qualification->name); ?>
<?php $__env->startSection('heading', $assignment->name); ?>
<?php $__env->startSection('breadcrumbs'); ?>
    <a href="<?php echo e(route('dashboard')); ?>" class="hover:text-gray-800 transition-colors">Dashboard</a>
    <span class="text-gray-300 mx-0.5">›</span>
    <a href="<?php echo e(route('qualifications.index')); ?>" class="hover:text-gray-800 transition-colors">Qualifications</a>
    <span class="text-gray-300 mx-0.5">›</span>
    <a href="<?php echo e(route('qualifications.show', $qualification)); ?>" class="hover:text-gray-800 transition-colors"><?php echo e($qualification->name); ?></a>
    <span class="text-gray-300 mx-0.5">›</span>
    <a href="<?php echo e(route('qualifications.assignments.index', $qualification)); ?>" class="hover:text-gray-800 transition-colors">Assignments</a>
    <span class="text-gray-300 mx-0.5">›</span>
    <span class="text-gray-700 font-medium"><?php echo e($assignment->name); ?></span>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('page-actions'); ?>
    <a href="<?php echo e(route('qualifications.assignments.index', $qualification)); ?>"
        class="inline-flex items-center gap-2 px-4 py-2 border border-gray-300 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-50">
        ← Assignments
    </a>
    <a href="<?php echo e(route('qualifications.assignments.edit', [$qualification, $assignment])); ?>"
        class="inline-flex items-center gap-2 px-4 py-2 border border-gray-300 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-50">
        Edit
    </a>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>
<div class="mt-2 space-y-5">

    <?php if(session('success')): ?>
        <div class="bg-green-50 border border-green-200 text-green-800 text-sm rounded-lg px-4 py-3"><?php echo e(session('success')); ?></div>
    <?php endif; ?>

    
    <div class="bg-white rounded-xl border border-gray-200 p-5">
        <div class="flex flex-wrap gap-6">
            <div>
                <p class="text-xs text-gray-500 font-medium">Type</p>
                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold mt-1
                    <?php echo e($assignment->type === 'summative' ? 'bg-purple-50 text-purple-700' : 'bg-blue-50 text-blue-700'); ?>">
                    <?php echo e(ucfirst($assignment->type)); ?>

                </span>
            </div>
            <div>
                <p class="text-xs text-gray-500 font-medium">Total Marks</p>
                <p class="text-sm font-semibold text-gray-800 mt-1">
                    <?php echo e($assignment->total_marks ?? '—'); ?>

                    <?php if($assignment->questions->isNotEmpty()): ?>
                        <span class="text-xs font-normal text-gray-400 ml-1">(from questions)</span>
                    <?php endif; ?>
                </p>
            </div>
            <div>
                <p class="text-xs text-gray-500 font-medium">Memo</p>
                <p class="text-sm font-semibold text-gray-800 mt-1">
                    <?php if($assignment->memo_type === 'questions'): ?>
                        <span class="inline-flex items-center gap-1 text-orange-600">Per-question (see below)</span>
                    <?php elseif($assignment->memo_type === 'pdf' && $assignment->memo_path): ?>
                        <a href="<?php echo e(route('qualifications.assignments.memo', [$qualification, $assignment])); ?>"
                            class="inline-flex items-center gap-1 text-red-600 hover:text-red-800">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 6a1 1 0 011-1h6a1 1 0 110 2H7a1 1 0 01-1-1zm1 3a1 1 0 100 2h6a1 1 0 100-2H7z" clip-rule="evenodd"/></svg>
                            Download PDF Memo
                        </a>
                    <?php elseif($assignment->memo_type === 'text'): ?>
                        Text (see below)
                    <?php else: ?>
                        No memo uploaded
                    <?php endif; ?>
                </p>
            </div>
            <div>
                <p class="text-xs text-gray-500 font-medium">Mapped Modules</p>
                <p class="text-sm font-semibold text-gray-800 mt-1"><?php echo e($assignment->qualificationModules->count()); ?></p>
            </div>
        </div>
        <?php if($assignment->description): ?>
            <p class="text-sm text-gray-600 mt-4 pt-4 border-t border-gray-100"><?php echo e($assignment->description); ?></p>
        <?php endif; ?>
    </div>

    
    <?php if($assignment->qualificationModules->isNotEmpty()): ?>
    <div>
        <h2 class="text-sm font-semibold text-gray-900 mb-2">Mapped to Modules</h2>
        <div class="bg-white rounded-xl border border-gray-200 divide-y divide-gray-100">
            <?php $__currentLoopData = $assignment->qualificationModules; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $mod): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <div class="px-5 py-3 flex items-center gap-3">
                <?php
                    $colors = ['KM'=>'bg-blue-100 text-blue-800','PM'=>'bg-green-100 text-green-800','WM'=>'bg-orange-100 text-orange-800','US'=>'bg-purple-100 text-purple-800','MOD'=>'bg-gray-100 text-gray-700'];
                    $cls = $colors[strtoupper($mod->module_type)] ?? 'bg-gray-100 text-gray-700';
                ?>
                <span class="inline-flex px-2 py-0.5 rounded text-xs font-bold <?php echo e($cls); ?>"><?php echo e(strtoupper($mod->module_type)); ?></span>
                <span class="text-xs text-gray-500 font-mono"><?php echo e($mod->module_code); ?></span>
                <span class="text-sm text-gray-800"><?php echo e($mod->title); ?></span>
            </div>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </div>
        <p class="text-xs text-gray-400 mt-2 px-1">
            Change mappings on the <a href="<?php echo e(route('qualifications.modules.index', $qualification)); ?>" class="text-blue-600 hover:underline">Modules page</a>.
        </p>
    </div>
    <?php else: ?>
    <div class="bg-amber-50 border border-amber-200 rounded-xl px-5 py-4 text-sm text-amber-800">
        <strong>Not mapped yet.</strong> Go to the
        <a href="<?php echo e(route('qualifications.modules.index', $qualification)); ?>" class="underline font-semibold">Modules page</a>
        to map this assignment to the qualification module(s) it covers. This is required for POE tracking.
    </div>
    <?php endif; ?>

    
    <div>
        <div class="flex items-center justify-between mb-2">
            <h2 class="text-sm font-semibold text-gray-900">
                Questions
                <?php if($assignment->questions->isNotEmpty()): ?>
                    <span class="ml-1.5 text-xs font-normal text-gray-400">
                        <?php echo e($assignment->questions->count()); ?> question<?php echo e($assignment->questions->count() === 1 ? '' : 's'); ?>

                        · <?php echo e($assignment->questions->sum('marks')); ?> marks total
                    </span>
                <?php endif; ?>
            </h2>
            <a href="<?php echo e(route('qualifications.assignments.questions.create', [$qualification, $assignment])); ?>"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium bg-[#1e3a5f] text-white rounded-lg hover:bg-[#e3b64d] hover:text-[#1e3a5f] transition-colors">
                + Add Question
            </a>
        </div>

        <?php if($assignment->questions->isEmpty()): ?>
            <div class="bg-white rounded-xl border border-dashed border-gray-300 px-5 py-8 text-center">
                <p class="text-sm text-gray-500 mb-1">No questions added yet.</p>
                <p class="text-xs text-gray-400">Add structured questions with model answers so the AI grader has precise, per-question anchors when marking submissions.</p>
                <a href="<?php echo e(route('qualifications.assignments.questions.create', [$qualification, $assignment])); ?>"
                    class="inline-flex items-center gap-1.5 mt-4 px-4 py-2 text-sm font-medium bg-[#1e3a5f] text-white rounded-lg hover:bg-[#e3b64d] hover:text-[#1e3a5f] transition-colors">
                    + Add First Question
                </a>
            </div>
        <?php else: ?>
            <div class="bg-white rounded-xl border border-gray-200 divide-y divide-gray-100" id="questions-list">
                <?php $__currentLoopData = $assignment->questions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $question): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <div class="px-5 py-4 flex items-start gap-4" data-question-id="<?php echo e($question->id); ?>">
                    
                    <div class="mt-0.5 cursor-grab text-gray-300 hover:text-gray-400 shrink-0 drag-handle" title="Drag to reorder">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M7 2a2 2 0 1 0 .001 4.001A2 2 0 0 0 7 2zm0 6a2 2 0 1 0 .001 4.001A2 2 0 0 0 7 8zm0 6a2 2 0 1 0 .001 4.001A2 2 0 0 0 7 14zm6-8a2 2 0 1 0-.001-4.001A2 2 0 0 0 13 6zm0 2a2 2 0 1 0 .001 4.001A2 2 0 0 0 13 8zm0 6a2 2 0 1 0 .001 4.001A2 2 0 0 0 13 14z"/>
                        </svg>
                    </div>

                    
                    <div class="shrink-0 w-16 text-center">
                        <?php if($question->label): ?>
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold bg-orange-50 text-orange-700"><?php echo e($question->label); ?></span>
                        <?php endif; ?>
                        <p class="text-xs text-gray-500 mt-1 font-medium"><?php echo e($question->marks); ?> mk<?php echo e($question->marks === 1 ? '' : 's'); ?></p>
                    </div>

                    
                    <div class="flex-1 min-w-0">
                        <p class="text-sm text-gray-800 font-medium leading-snug"><?php echo e(Str::limit($question->question_text, 150)); ?></p>
                        <?php if($question->expected_answer): ?>
                            <details class="mt-2">
                                <summary class="text-xs text-gray-400 cursor-pointer hover:text-gray-600 select-none">Model answer ▸</summary>
                                <pre class="mt-2 text-xs text-gray-600 whitespace-pre-wrap font-mono bg-gray-50 rounded-lg p-3 leading-relaxed"><?php echo e($question->expected_answer); ?></pre>
                            </details>
                        <?php endif; ?>
                        <?php if($question->ai_grading_notes): ?>
                            <details class="mt-1">
                                <summary class="text-xs text-blue-400 cursor-pointer hover:text-blue-600 select-none">AI grading notes ▸</summary>
                                <p class="mt-1 text-xs text-gray-600 bg-blue-50 rounded-lg p-2 leading-relaxed"><?php echo e($question->ai_grading_notes); ?></p>
                            </details>
                        <?php endif; ?>
                    </div>

                    
                    <div class="shrink-0 flex items-center gap-2">
                        <a href="<?php echo e(route('qualifications.assignments.questions.edit', [$qualification, $assignment, $question])); ?>"
                            class="text-xs text-gray-500 hover:text-gray-800 px-2 py-1 rounded hover:bg-gray-100 transition-colors">Edit</a>
                        <form method="POST"
                              action="<?php echo e(route('qualifications.assignments.questions.destroy', [$qualification, $assignment, $question])); ?>"
                              onsubmit="return confirm('Delete this question?')">
                            <?php echo csrf_field(); ?> <?php echo method_field('DELETE'); ?>
                            <button type="submit" class="text-xs text-red-400 hover:text-red-700 px-2 py-1 rounded hover:bg-red-50 transition-colors">Delete</button>
                        </form>
                    </div>
                </div>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </div>

            <p class="text-xs text-gray-400 mt-2 px-1">Drag rows to reorder, or set the order number when editing a question.</p>
        <?php endif; ?>
    </div>

    
    <?php if($assignment->memo_type === 'text' && $assignment->memo_text): ?>
    <div>
        <h2 class="text-sm font-semibold text-gray-900 mb-2">Assignment-level Marking Memo</h2>
        <div class="bg-white rounded-xl border border-gray-200 p-5">
            <pre class="text-xs text-gray-700 whitespace-pre-wrap font-mono leading-relaxed"><?php echo e($assignment->memo_text); ?></pre>
        </div>
    </div>
    <?php endif; ?>

</div>

<script>
(function () {
    const list = document.getElementById('questions-list');
    if (!list) return;

    const reorderUrl = <?php echo json_encode(route('qualifications.assignments.questions.reorder', [$qualification, $assignment])) ?>;
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

    let dragging = null;

    list.querySelectorAll('.drag-handle').forEach(handle => {
        const row = handle.closest('[data-question-id]');
        row.draggable = true;

        row.addEventListener('dragstart', e => {
            dragging = row;
            e.dataTransfer.effectAllowed = 'move';
            setTimeout(() => row.classList.add('opacity-40'), 0);
        });

        row.addEventListener('dragend', () => {
            dragging = null;
            row.classList.remove('opacity-40');
            sendReorder();
        });
    });

    list.addEventListener('dragover', e => {
        e.preventDefault();
        const target = e.target.closest('[data-question-id]');
        if (!target || target === dragging) return;
        const rect = target.getBoundingClientRect();
        const insertBefore = e.clientY < rect.top + rect.height / 2;
        list.insertBefore(dragging, insertBefore ? target : target.nextSibling);
    });

    function sendReorder() {
        const ids = [...list.querySelectorAll('[data-question-id]')].map(r => r.dataset.questionId);
        fetch(reorderUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
            },
            body: JSON.stringify({ order: ids }),
        });
    }
})();
</script>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /home/runner/workspace/ams/resources/views/assignments/show.blade.php ENDPATH**/ ?>