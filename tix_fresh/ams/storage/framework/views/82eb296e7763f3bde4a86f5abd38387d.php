<?php $__env->startSection('title', 'Qualifications — TiXMark IQ'); ?>
<?php $__env->startSection('heading', 'Qualifications'); ?>
<?php $__env->startSection('breadcrumbs'); ?>
    <a href="<?php echo e(route('dashboard')); ?>" class="hover:text-gray-800 transition-colors">Dashboard</a>
    <span class="text-gray-300 mx-0.5">›</span>
    <span class="text-gray-700 font-medium">Qualifications</span>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('page-actions'); ?>
    <a href="<?php echo e(route('qualifications.create')); ?>"
        class="inline-flex items-center gap-2 px-4 py-2 hover:bg-[#e3b64d] hover:text-[#1e3a5f] text-white text-sm font-medium rounded-lg transition-colors bg-[#1e3a5f]">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        New Qualification
    </a>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>
<div class="mt-2">
<?php if($qualifications->isEmpty()): ?>
    <div class="bg-white rounded-xl border border-gray-200 p-12 text-center">
        <svg class="w-10 h-10 text-gray-300 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
        </svg>
        <p class="text-sm text-gray-500 mb-4">No qualifications yet.</p>
        <a href="<?php echo e(route('qualifications.create')); ?>" class="inline-flex items-center gap-2 px-4 py-2 bg-[#1e3a5f] text-white text-sm font-medium rounded-lg hover:bg-[#e3b64d] hover:text-[#1e3a5f]">
            Add your first qualification
        </a>
    </div>
<?php else: ?>
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-gray-100 bg-gray-50 text-left">
                    <th class="px-5 py-3 text-xs font-semibold text-gray-600 uppercase tracking-wide">Qualification</th>
                    <th class="px-5 py-3 text-xs font-semibold text-gray-600 uppercase tracking-wide">SAQA ID</th>
                    <th class="px-5 py-3 text-xs font-semibold text-gray-600 uppercase tracking-wide">NQF</th>
                    <th class="px-5 py-3 text-xs font-semibold text-gray-600 uppercase tracking-wide">Track</th>
                    <th class="px-5 py-3 text-xs font-semibold text-gray-600 uppercase tracking-wide">SETA</th>
                    <th class="px-5 py-3 text-xs font-semibold text-gray-600 uppercase tracking-wide">Cohorts</th>
                    <th class="px-5 py-3 text-xs font-semibold text-gray-600 uppercase tracking-wide">Status</th>
                    <th class="px-5 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php $__currentLoopData = $qualifications; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $q): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <tr class="hover:bg-gray-50 transition-colors">
                    <td class="px-5 py-3">
                        <a href="<?php echo e(route('qualifications.show', $q)); ?>" class="font-medium text-gray-900 hover:text-orange-600">
                            <?php echo e($q->name); ?>

                        </a>
                    </td>
                    <td class="px-5 py-3 text-gray-500"><?php echo e($q->saqa_id ?? '—'); ?></td>
                    <td class="px-5 py-3">
                        <span class="inline-flex items-center justify-center w-7 h-7 bg-navy-900 text-white text-xs font-bold rounded-lg" style="background:#1e3a5f"><?php echo e($q->nqf_level); ?></span>
                    </td>
                    <td class="px-5 py-3">
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium <?php echo e($q->track === 'qcto_occupational' ? 'bg-blue-50 text-blue-700' : 'bg-purple-50 text-purple-700'); ?>">
                            <?php echo e($q->trackLabel()); ?>

                        </span>
                    </td>
                    <td class="px-5 py-3 text-gray-600"><?php echo e($q->seta); ?></td>
                    <td class="px-5 py-3 text-gray-600"><?php echo e($q->cohorts_count); ?></td>
                    <td class="px-5 py-3">
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium <?php echo e($q->status === 'active' ? 'bg-green-50 text-green-700' : 'bg-gray-100 text-gray-500'); ?>">
                            <?php echo e(ucfirst($q->status)); ?>

                        </span>
                    </td>
                    <td class="px-5 py-3 text-right">
                        <a href="<?php echo e(route('qualifications.edit', $q)); ?>" class="text-xs text-gray-400 hover:text-gray-700">Edit</a>
                    </td>
                </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /home/runner/workspace/ams/resources/views/qualifications/index.blade.php ENDPATH**/ ?>