<?php $__env->startSection('title', 'Edit Qualification — AjanaNova AMS'); ?>
<?php $__env->startSection('heading', 'Edit Qualification'); ?>
<?php $__env->startSection('breadcrumb', 'Qualifications → Edit'); ?>

<?php $__env->startSection('content'); ?>
<div class="max-w-2xl mt-2">
    <div class="bg-white rounded-xl border border-gray-200 p-6">
        <form method="POST" action="<?php echo e(route('qualifications.update', $qualification)); ?>" class="space-y-5">
            <?php echo csrf_field(); ?>
            <?php echo method_field('PUT'); ?>

            <div class="grid grid-cols-2 gap-4">
                <div class="col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Qualification name <span class="text-red-500">*</span></label>
                    <input type="text" name="name" value="<?php echo e(old('name', $qualification->name)); ?>" required
                        class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-orange-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">SAQA ID</label>
                    <input type="text" name="saqa_id" value="<?php echo e(old('saqa_id', $qualification->saqa_id)); ?>"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-orange-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">NQF Level <span class="text-red-500">*</span></label>
                    <select name="nqf_level" required class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-orange-500">
                        <?php for($i = 1; $i <= 10; $i++): ?>
                            <option value="<?php echo e($i); ?>" <?php echo e(old('nqf_level', $qualification->nqf_level) == $i ? 'selected' : ''); ?>>Level <?php echo e($i); ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Track <span class="text-red-500">*</span></label>
                    <select name="track" required class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-orange-500">
                        <option value="qcto_occupational" <?php echo e(old('track', $qualification->track) === 'qcto_occupational' ? 'selected' : ''); ?>>QCTO Occupational</option>
                        <option value="legacy_seta" <?php echo e(old('track', $qualification->track) === 'legacy_seta' ? 'selected' : ''); ?>>Legacy SETA</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Credits</label>
                    <input type="number" name="credits" value="<?php echo e(old('credits', $qualification->credits)); ?>" min="1"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-orange-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">SETA <span class="text-red-500">*</span></label>
                    <select name="seta" required class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-orange-500">
                        <?php $__currentLoopData = ['MICT', 'ETDP', 'SERVICES', 'BANKSETA', 'CATHSSETA', 'CETA', 'CHIETA', 'EWSETA', 'FASSET', 'FIETA', 'FoodBev', 'HWSETA', 'INSETA', 'LGSETA', 'MAPPP', 'MQA', 'MERSETA', 'POSHEITA', 'PSETA', 'RCL', 'SASSETA', 'TETA', 'W&RSETA']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $s): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <option value="<?php echo e($s); ?>" <?php echo e(old('seta', $qualification->seta) === $s ? 'selected' : ''); ?>><?php echo e($s); ?></option>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </select>
                </div>
                <div class="col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">SETA Registration Number</label>
                    <input type="text" name="seta_registration_number" value="<?php echo e(old('seta_registration_number', $qualification->seta_registration_number)); ?>"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-orange-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Status</label>
                    <select name="status" class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-orange-500">
                        <option value="active" <?php echo e(old('status', $qualification->status) === 'active' ? 'selected' : ''); ?>>Active</option>
                        <option value="archived" <?php echo e(old('status', $qualification->status) === 'archived' ? 'selected' : ''); ?>>Archived</option>
                    </select>
                </div>
                <div class="col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Notes</label>
                    <textarea name="notes" rows="3"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-orange-500"><?php echo e(old('notes', $qualification->notes)); ?></textarea>
                </div>
            </div>

            <div class="flex items-center gap-3 pt-2 border-t border-gray-100">
                <button type="submit" class="px-5 py-2.5 bg-orange-600 hover:bg-orange-700 text-white text-sm font-medium rounded-lg transition-colors">
                    Save Changes
                </button>
                <a href="<?php echo e(route('qualifications.show', $qualification)); ?>" class="px-5 py-2.5 text-sm text-gray-600 hover:text-gray-900">Cancel</a>
            </div>
        </form>
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /home/runner/workspace/ams/resources/views/qualifications/edit.blade.php ENDPATH**/ ?>