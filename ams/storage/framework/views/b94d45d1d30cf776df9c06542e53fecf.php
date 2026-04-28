<?php $__env->startSection('title', 'POE — ' . $learner->full_name); ?>
<?php $__env->startSection('heading', 'POE: ' . $learner->full_name); ?>
<?php $__env->startSection('breadcrumbs'); ?>
    <a href="<?php echo e(route('dashboard')); ?>" class="hover:text-gray-800 transition-colors">Dashboard</a>
    <span class="text-gray-300 mx-0.5">›</span>
    <a href="<?php echo e(route('qualifications.index')); ?>" class="hover:text-gray-800 transition-colors">Qualifications</a>
    <span class="text-gray-300 mx-0.5">›</span>
    <a href="<?php echo e(route('qualifications.show', $qualification)); ?>" class="hover:text-gray-800 transition-colors"><?php echo e($qualification->name); ?></a>
    <span class="text-gray-300 mx-0.5">›</span>
    <a href="<?php echo e(route('qualifications.cohorts.show', [$qualification, $cohort])); ?>" class="hover:text-gray-800 transition-colors"><?php echo e($cohort->name); ?></a>
    <span class="text-gray-300 mx-0.5">›</span>
    <a href="<?php echo e(route('qualifications.cohorts.learners.index', [$qualification, $cohort])); ?>" class="hover:text-gray-800 transition-colors">Learners</a>
    <span class="text-gray-300 mx-0.5">›</span>
    <span class="text-gray-700 font-medium"><?php echo e($learner->full_name); ?></span>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>


<?php if(session('success')): ?>
<div class="mb-4 px-4 py-3 rounded-lg bg-green-50 border border-green-200 text-green-800 text-sm">
    <?php echo e(session('success')); ?>

</div>
<?php endif; ?>
<?php if(session('info')): ?>
<div class="mb-4 px-4 py-3 rounded-lg bg-blue-50 border border-blue-200 text-blue-800 text-sm">
    <?php echo e(session('info')); ?>

</div>
<?php endif; ?>
<?php if($errors->any()): ?>
<div class="mb-4 px-4 py-3 rounded-lg bg-red-50 border border-red-200 text-red-800 text-sm">
    <?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $e): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><div><?php echo e($e); ?></div><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
</div>
<?php endif; ?>


<div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 mb-6">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900"><?php echo e($learner->full_name); ?></h1>
            <div class="mt-1 flex flex-wrap gap-3 text-sm text-gray-500">
                <?php if($learner->email): ?><span><?php echo e($learner->email); ?></span><?php endif; ?>
                <?php if($learner->external_ref): ?><span>&bull; Ref: <?php echo e($learner->external_ref); ?></span><?php endif; ?>
                <span>&bull; Cohort: <?php echo e($cohort->name); ?> (<?php echo e($cohort->year); ?>)</span>
            </div>
            <div class="mt-2 text-sm text-gray-600">
                <span class="font-medium">Qualification:</span>
                <?php echo e($qualification->name); ?>

                <?php if($qualification->saqa_id): ?> &mdash; SAQA <?php echo e($qualification->saqa_id); ?><?php endif; ?>
                <?php if($qualification->nqf_level): ?> &mdash; NQF Level <?php echo e($qualification->nqf_level); ?><?php endif; ?>
            </div>
        </div>

        
        <?php
            $cCount      = count(array_filter($moduleStatuses, fn($s) => $s['status'] === 'C'));
            $nycCount    = count(array_filter($moduleStatuses, fn($s) => $s['status'] === 'NYC'));
            $totalMapped = count(array_filter($moduleStatuses, fn($s) => $s['status'] !== 'unmapped'));
        ?>
        <div class="flex gap-3 flex-wrap">
            <div class="text-center px-4 py-2 rounded-lg bg-green-50 border border-green-200">
                <div class="text-2xl font-bold text-green-700"><?php echo e($cCount); ?></div>
                <div class="text-xs text-green-600 font-medium">Competent</div>
            </div>
            <div class="text-center px-4 py-2 rounded-lg bg-red-50 border border-red-200">
                <div class="text-2xl font-bold text-red-700"><?php echo e($nycCount); ?></div>
                <div class="text-xs text-red-600 font-medium">NYC</div>
            </div>
            <div class="text-center px-4 py-2 rounded-lg bg-gray-50 border border-gray-200">
                <div class="text-2xl font-bold text-gray-700"><?php echo e($totalMapped - $cCount - $nycCount); ?></div>
                <div class="text-xs text-gray-500 font-medium">Pending</div>
            </div>
        </div>
    </div>

    <div class="mt-4 pt-4 border-t border-gray-100 flex gap-3">
        <button onclick="window.print()"
                class="no-print text-xs bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold px-4 py-1.5 rounded-lg transition">
            Print / Export PDF
        </button>
        <span class="text-xs text-gray-400 self-center">Generated: <?php echo e(now()->format('d M Y H:i')); ?></span>
    </div>
</div>

<?php if($modules->isEmpty()): ?>
    <div class="bg-white rounded-xl border border-dashed border-gray-300 p-12 text-center">
        <p class="text-gray-500 text-sm">No qualification modules defined yet.</p>
        <a href="<?php echo e(route('qualifications.modules.index', $qualification)); ?>"
           class="mt-3 inline-block text-sm text-orange-600 hover:underline">
            Set up qualification modules →
        </a>
    </div>
<?php else: ?>


<div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden mb-6">

    <div class="px-5 py-3 bg-gray-50 border-b border-gray-200 flex items-center justify-between">
        <div>
            <h2 class="font-semibold text-gray-800 text-sm">Portfolio of Evidence — Module Tracking</h2>
            <p class="text-xs text-gray-500 mt-0.5">Per QCTO/SAQA requirements</p>
        </div>
        <span class="no-print text-xs px-2 py-0.5 rounded bg-orange-100 text-orange-700 font-semibold">MOCK MODE</span>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-gray-50 border-b border-gray-200 text-xs font-semibold text-gray-500 uppercase tracking-wide">
                    <th class="px-4 py-3 text-left w-8">#</th>
                    <th class="px-4 py-3 text-left w-16">Type</th>
                    <th class="px-4 py-3 text-left">Code</th>
                    <th class="px-4 py-3 text-left">Module / Unit Standard</th>
                    <th class="px-4 py-3 text-center w-14">NQF</th>
                    <th class="px-4 py-3 text-center w-14">Credits</th>
                    <th class="px-4 py-3 text-left">Assignment &amp; Submission</th>
                    <th class="px-4 py-3 text-center w-32">Result</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php $__currentLoopData = $modules; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $mod): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <?php
                    $modStatus = $moduleStatuses[$mod->id] ?? ['status' => 'unmapped', 'label' => 'Not mapped', 'assignments' => []];
                    $colors = [
                        'KM'  => 'bg-blue-100 text-blue-800',
                        'PM'  => 'bg-green-100 text-green-800',
                        'WM'  => 'bg-orange-100 text-orange-800',
                        'US'  => 'bg-purple-100 text-purple-800',
                        'MOD' => 'bg-gray-100 text-gray-700',
                    ];
                    $badgeCls = $colors[strtoupper($mod->module_type)] ?? 'bg-gray-100 text-gray-700';
                    $resultCls = match($modStatus['status']) {
                        'C'       => 'bg-green-100 text-green-800 font-bold',
                        'NYC'     => 'bg-red-100 text-red-800 font-bold',
                        'partial' => 'bg-yellow-100 text-yellow-800',
                        'unmapped'=> 'bg-gray-50 text-gray-400 italic',
                        default   => 'bg-gray-100 text-gray-600',
                    };
                    $resultLabel = match($modStatus['status']) {
                        'C'       => 'C — Competent',
                        'NYC'     => 'NYC',
                        'partial' => $modStatus['label'],
                        'unmapped'=> '—',
                        default   => 'Pending',
                    };
                ?>
                <tr class="hover:bg-gray-50 transition-colors align-top">
                    <td class="px-4 py-3 text-gray-400 text-xs"><?php echo e($mod->sortorder); ?></td>
                    <td class="px-4 py-3">
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold <?php echo e($badgeCls); ?>">
                            <?php echo e(strtoupper($mod->module_type)); ?>

                        </span>
                    </td>
                    <td class="px-4 py-3">
                        <code class="text-xs text-gray-500"><?php echo e($mod->module_code); ?></code>
                    </td>
                    <td class="px-4 py-3 font-medium text-gray-800 leading-snug"><?php echo e($mod->title); ?></td>
                    <td class="px-4 py-3 text-center text-gray-500"><?php echo e($mod->nqf_level ?: '—'); ?></td>
                    <td class="px-4 py-3 text-center text-gray-500"><?php echo e($mod->credits ?: '—'); ?></td>

                    
                    <td class="px-4 py-3">
                        <?php if(empty($modStatus['assignments'])): ?>
                            <span class="text-xs text-gray-400 italic">Not mapped to any assignment</span>
                        <?php else: ?>
                            <div class="space-y-3">
                            <?php $__currentLoopData = $modStatus['assignments']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <?php
                                $asgn      = $item['assignment'];
                                $sub       = $item['submission'];
                                $verdict   = $item['verdict'];
                                $subStatus = $item['submission_status'];
                                $uploadId  = 'upload-form-' . $asgn->id;
                            ?>
                            <div class="border border-gray-200 rounded-lg p-2.5 bg-white">
                                
                                <div class="flex items-center gap-2 mb-2 flex-wrap">
                                    <span class="text-xs font-semibold text-gray-800"><?php echo e($asgn->name); ?></span>
                                    <span class="text-xs px-1.5 py-0.5 rounded
                                        <?php echo e($asgn->type === 'formative' ? 'bg-blue-50 text-blue-600' : 'bg-purple-50 text-purple-600'); ?>">
                                        <?php echo e(ucfirst($asgn->type)); ?>

                                    </span>
                                    <span class="text-xs text-gray-400"><?php echo e($asgn->total_marks); ?> marks</span>
                                    <?php if($asgn->lms_connection_id): ?>
                                    <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-xs font-semibold bg-orange-100 text-orange-700 border border-orange-200">
                                        <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                                        Moodle
                                    </span>
                                    <?php endif; ?>
                                </div>

                                
                                <?php if(! $sub): ?>
                                    <div class="flex items-center gap-2 no-print">
                                        <span class="text-xs text-gray-400">No submission yet</span>
                                        <button type="button"
                                                onclick="toggleUpload('<?php echo e($uploadId); ?>')"
                                                class="text-xs hover:bg-[#e3b64d] hover:text-[#1e3a5f] bg-[#e3b64d] text-white font-semibold px-3 py-1 rounded transition">
                                            Upload Submission
                                        </button>
                                    </div>
                                    <div id="<?php echo e($uploadId); ?>" class="hidden mt-2 no-print">
                                        <form method="POST" enctype="multipart/form-data"
                                              action="<?php echo e(route('qualifications.cohorts.learners.submissions.store', [$qualification, $cohort, $learner])); ?>">
                                            <?php echo csrf_field(); ?>
                                            <input type="hidden" name="assignment_id" value="<?php echo e($asgn->id); ?>">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <input type="file" name="submission_file" required
                                                       accept=".pdf,.doc,.docx,.txt,.png,.jpg,.jpeg,.zip,.odt"
                                                       class="text-xs text-gray-600 file:mr-2 file:py-1 file:px-3 file:rounded file:border-0 file:text-xs file:font-semibold file:bg-orange-50 file:text-orange-700 hover:file:bg-orange-100 flex-1 min-w-0">
                                                <button type="submit"
                                                        class="text-xs bg-green-600 hover:bg-green-700 text-white font-semibold px-3 py-1 rounded transition shrink-0">
                                                    Upload
                                                </button>
                                                <button type="button" onclick="toggleUpload('<?php echo e($uploadId); ?>')"
                                                        class="text-xs text-gray-400 hover:text-gray-600 px-1">Cancel</button>
                                            </div>
                                            <p class="mt-1 text-xs text-gray-400">PDF, Word, TXT, image or ZIP — max 20 MB</p>
                                        </form>
                                    </div>

                                
                                <?php elseif($subStatus === 'uploaded'): ?>
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <span class="inline-flex items-center gap-1 text-xs px-2 py-0.5 rounded-full bg-gray-100 text-gray-600 font-medium">
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                            <?php echo e($sub->original_filename); ?>

                                        </span>
                                        <?php if($sub->isFromMoodle()): ?>
                                        <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-xs font-semibold bg-orange-100 text-orange-700 border border-orange-200">
                                            <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                                            From Moodle
                                        </span>
                                        <?php endif; ?>
                                        <form method="POST" class="no-print"
                                              action="<?php echo e(route('qualifications.cohorts.learners.submissions.mark', [$qualification, $cohort, $learner, $sub])); ?>">
                                            <?php echo csrf_field(); ?>
                                            <button type="submit"
                                                    class="text-xs bg-blue-600 hover:bg-blue-700 text-white font-semibold px-3 py-1 rounded transition">
                                                Run AI Marking
                                            </button>
                                        </form>
                                        <form method="POST" class="no-print"
                                              action="<?php echo e(route('qualifications.cohorts.learners.submissions.destroy', [$qualification, $cohort, $learner, $sub])); ?>"
                                              onsubmit="return confirm('Delete this submission?')">
                                            <?php echo csrf_field(); ?> <?php echo method_field('DELETE'); ?>
                                            <button type="submit" class="text-xs text-red-400 hover:text-red-600">Remove</button>
                                        </form>
                                    </div>

                                
                                <?php elseif(in_array($subStatus, ['review_required', 'marking', 'queued'])): ?>
                                    <?php
                                        $badge     = $sub->statusBadge();
                                        $replaceId = 'replace-form-' . $asgn->id;
                                    ?>
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <span class="text-xs px-2 py-0.5 rounded-full font-medium <?php echo e($badge['class']); ?>"><?php echo e($badge['label']); ?></span>
                                        <?php if($sub->markingResult): ?>
                                            <span class="text-xs font-semibold
                                                <?php echo e($sub->markingResult->ai_recommendation === 'COMPETENT' ? 'text-green-700' : 'text-red-700'); ?>">
                                                AI: <?php echo e($sub->markingResult->ai_recommendation === 'COMPETENT' ? 'Competent' : 'NYC'); ?>

                                                (<?php echo e($sub->markingResult->confidence); ?>)
                                            </span>
                                        <?php endif; ?>
                                        <a href="<?php echo e(route('qualifications.cohorts.learners.submissions.show', [$qualification, $cohort, $learner, $sub])); ?>"
                                           class="no-print text-xs hover:bg-[#e3b64d] hover:text-[#1e3a5f] bg-[#e3b64d] text-white font-semibold px-3 py-1 rounded transition">
                                            Review &amp; Sign Off →
                                        </a>
                                        <button type="button" onclick="toggleUpload('<?php echo e($replaceId); ?>')"
                                                class="no-print text-xs text-gray-400 hover:text-gray-700 border border-gray-300 px-2 py-0.5 rounded transition">
                                            Replace file
                                        </button>
                                    </div>
                                    
                                    <div id="<?php echo e($replaceId); ?>" class="hidden mt-2 no-print">
                                        <div class="text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded px-2 py-1 mb-1.5">
                                            Replacing will delete the current submission and marking result. You will need to run AI Marking again.
                                        </div>
                                        <form method="POST" enctype="multipart/form-data"
                                              action="<?php echo e(route('qualifications.cohorts.learners.submissions.store', [$qualification, $cohort, $learner])); ?>">
                                            <?php echo csrf_field(); ?>
                                            <input type="hidden" name="assignment_id" value="<?php echo e($asgn->id); ?>">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <input type="file" name="submission_file" required
                                                       accept=".pdf,.doc,.docx,.txt,.png,.jpg,.jpeg,.zip,.odt"
                                                       class="text-xs text-gray-600 file:mr-2 file:py-1 file:px-3 file:rounded file:border-0 file:text-xs file:font-semibold file:bg-orange-50 file:text-orange-700 hover:file:bg-orange-100 flex-1 min-w-0">
                                                <button type="submit"
                                                        class="text-xs bg-amber-600 hover:bg-amber-700 text-white font-semibold px-3 py-1 rounded transition shrink-0">
                                                    Replace
                                                </button>
                                                <button type="button" onclick="toggleUpload('<?php echo e($replaceId); ?>')"
                                                        class="text-xs text-gray-400 hover:text-gray-600 px-1">Cancel</button>
                                            </div>
                                        </form>
                                    </div>

                                
                                <?php elseif($subStatus === 'signed_off'): ?>
                                    <?php $replaceId = 'replace-signed-' . $asgn->id; ?>
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <span class="text-xs font-bold <?php echo e($verdict === 'COMPETENT' ? 'text-green-700' : 'text-red-700'); ?>">
                                            <?php echo e($verdict === 'COMPETENT' ? '✓ COMPETENT' : '✗ NOT YET COMPETENT'); ?>

                                        </span>
                                        <span class="text-xs text-gray-400">signed <?php echo e($sub->signed_off_at?->format('d M Y')); ?></span>
                                        <a href="<?php echo e(route('qualifications.cohorts.learners.submissions.show', [$qualification, $cohort, $learner, $sub])); ?>"
                                           class="no-print text-xs text-blue-600 hover:underline">View</a>
                                        <button type="button" onclick="toggleUpload('<?php echo e($replaceId); ?>')"
                                                class="no-print text-xs text-gray-400 hover:text-gray-700 border border-gray-300 px-2 py-0.5 rounded transition">
                                            Re-submit
                                        </button>
                                    </div>
                                    
                                    <div id="<?php echo e($replaceId); ?>" class="hidden mt-2 no-print">
                                        <div class="text-xs text-red-700 bg-red-50 border border-red-200 rounded px-2 py-1 mb-1.5">
                                            This will reopen the signed-off result and require re-marking. Use only for genuine resubmissions.
                                        </div>
                                        <form method="POST" enctype="multipart/form-data"
                                              action="<?php echo e(route('qualifications.cohorts.learners.submissions.store', [$qualification, $cohort, $learner])); ?>">
                                            <?php echo csrf_field(); ?>
                                            <input type="hidden" name="assignment_id" value="<?php echo e($asgn->id); ?>">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <input type="file" name="submission_file" required
                                                       accept=".pdf,.doc,.docx,.txt,.png,.jpg,.jpeg,.zip,.odt"
                                                       class="text-xs text-gray-600 file:mr-2 file:py-1 file:px-3 file:rounded file:border-0 file:text-xs file:font-semibold file:bg-orange-50 file:text-orange-700 hover:file:bg-orange-100 flex-1 min-w-0">
                                                <button type="submit"
                                                        class="text-xs bg-red-600 hover:bg-red-700 text-white font-semibold px-3 py-1 rounded transition shrink-0">
                                                    Re-submit &amp; Reopen
                                                </button>
                                                <button type="button" onclick="toggleUpload('<?php echo e($replaceId); ?>')"
                                                        class="text-xs text-gray-400 hover:text-gray-600 px-1">Cancel</button>
                                            </div>
                                        </form>
                                    </div>
                                <?php else: ?>
                                    <span class="text-xs text-gray-400"><?php echo e(ucfirst(str_replace('_', ' ', $subStatus ?? ''))); ?></span>
                                <?php endif; ?>

                            </div>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                            </div>
                        <?php endif; ?>
                    </td>

                    
                    <td class="px-4 py-3 text-center align-middle">
                        <span class="inline-flex items-center justify-center px-3 py-1 rounded-full text-xs <?php echo e($resultCls); ?>">
                            <?php echo e($resultLabel); ?>

                        </span>
                    </td>
                </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </tbody>
        </table>
    </div>

    <div class="px-5 py-3 bg-gray-50 border-t border-gray-200 flex flex-wrap items-center justify-between gap-2">
        <div class="text-xs text-gray-500">
            Total: <strong><?php echo e($modules->count()); ?></strong> modules &bull;
            <strong><?php echo e($modules->sum('credits')); ?></strong> credits
        </div>
        <div class="flex gap-4 text-xs">
            <span class="text-green-700 font-semibold"><?php echo e($cCount); ?> Competent</span>
            <span class="text-red-700 font-semibold"><?php echo e($nycCount); ?> NYC</span>
            <span class="text-gray-500"><?php echo e($totalMapped - $cCount - $nycCount); ?> Pending</span>
        </div>
    </div>
</div>


<div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
    <h3 class="font-semibold text-gray-800 mb-3">Assessor Declaration</h3>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 text-sm text-gray-700">
        <div>
            <div class="border-b border-gray-300 pb-1 mb-1 text-xs text-gray-400">Assessor Name</div>
            <div class="h-6"><?php echo e(auth()->user()->name); ?></div>
        </div>
        <div>
            <div class="border-b border-gray-300 pb-1 mb-1 text-xs text-gray-400">Date</div>
            <div class="h-6"><?php echo e(now()->format('d F Y')); ?></div>
        </div>
        <div>
            <div class="border-b border-gray-300 pb-1 mb-1 text-xs text-gray-400">Assessor Signature</div>
            <div class="h-10"></div>
        </div>
        <div>
            <div class="border-b border-gray-300 pb-1 mb-1 text-xs text-gray-400">Moderator Signature (if applicable)</div>
            <div class="h-10"></div>
        </div>
    </div>
    <p class="mt-4 text-xs text-gray-400">
        I confirm that the above-named learner has been assessed against the stated qualification modules and that the results recorded
        are a true and fair reflection of the learner's competence. This record is maintained for the mandatory 5-year retention period
        in accordance with SAQA/QCTO requirements.
    </p>
</div>

<?php endif; ?>

<script>
function toggleUpload(id) {
    const el = document.getElementById(id);
    if (el) el.classList.toggle('hidden');
}
</script>

<style>
@media print {
    .no-print, nav, aside, button { display: none !important; }
    body { font-size: 11pt; }
}
</style>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /home/runner/workspace/ams/resources/views/learners/poe.blade.php ENDPATH**/ ?>