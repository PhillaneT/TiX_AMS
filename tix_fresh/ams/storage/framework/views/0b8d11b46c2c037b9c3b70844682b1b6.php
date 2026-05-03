<?php $__env->startSection('title', 'Add LMS Connection'); ?>
<?php $__env->startSection('heading', 'Add Moodle Connection'); ?>
<?php $__env->startSection('breadcrumbs'); ?>
    <a href="<?php echo e(route('dashboard')); ?>" class="hover:text-gray-800 transition-colors">Dashboard</a>
    <span class="text-gray-300 mx-0.5">›</span>
    <a href="<?php echo e(route('integrations.index')); ?>" class="hover:text-gray-800 transition-colors">LMS Integrations</a>
    <span class="text-gray-300 mx-0.5">›</span>
    <span class="text-gray-700 font-medium">New Connection</span>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('page-actions'); ?>
    <a href="<?php echo e(route('integrations.index')); ?>"
       class="inline-flex items-center gap-2 px-4 py-2 border border-gray-300 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-50">
        ← Back
    </a>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>
<div class="max-w-xl mt-2">
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">

        <?php if($errors->any()): ?>
        <div class="mb-4 px-4 py-3 rounded-lg bg-red-50 border border-red-200 text-red-800 text-sm">
            <?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $e): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><div><?php echo e($e); ?></div><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </div>
        <?php endif; ?>

        <form method="POST" action="<?php echo e(route('integrations.store')); ?>" class="space-y-5">
            <?php echo csrf_field(); ?>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Connection Label <span class="text-red-500">*</span>
                </label>
                <input type="text" name="label" value="<?php echo e(old('label')); ?>" required
                       placeholder="e.g. TVET Moodle 2026"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-orange-400">
                <p class="mt-1 text-xs text-gray-400">A friendly name to identify this connection.</p>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Moodle Site URL <span class="text-red-500">*</span>
                </label>
                <input type="url" name="base_url" value="<?php echo e(old('base_url')); ?>" required
                       placeholder="https://moodle.example.com"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-orange-400">
                <p class="mt-1 text-xs text-gray-400">The full URL of your Moodle site (no trailing slash needed).</p>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    API Token <span class="text-red-500">*</span>
                </label>
                <input type="password" name="api_token" required autocomplete="off"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-orange-400 font-mono">
                <p class="mt-1 text-xs text-gray-400">
                    Create a Web Services token in Moodle: <em>Site Administration → Plugins → Web Services → Manage tokens</em>.
                    The token user needs the <strong>mod/assign:grade</strong> capability.
                    The token is stored encrypted.
                </p>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Course IDs
                </label>
                <input type="text" name="course_ids" value="<?php echo e(old('course_ids')); ?>"
                       placeholder="e.g. 12, 15, 23"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-orange-400 font-mono">
                <p class="mt-1 text-xs text-gray-400">
                    Comma-separated Moodle course IDs to sync. You can find a course ID in its URL: <code class="bg-gray-100 px-1 rounded">/course/view.php?id=<strong>12</strong></code>.
                    You can add these later.
                </p>
            </div>

            <div class="pt-2 flex gap-3">
                <button type="submit"
                        class="px-5 py-2 bg-brand-600 hover:bg-brand-700 text-white text-sm font-semibold rounded-lg transition">
                    Save Connection
                </button>
                <a href="<?php echo e(route('integrations.index')); ?>"
                   class="px-5 py-2 border border-gray-300 text-gray-600 text-sm rounded-lg hover:bg-gray-50 transition">
                    Cancel
                </a>
            </div>
        </form>
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /home/runner/workspace/ams/resources/views/integrations/create.blade.php ENDPATH**/ ?>