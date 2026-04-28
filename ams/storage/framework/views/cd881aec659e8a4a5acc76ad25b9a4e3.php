<?php $__env->startSection('title', 'Edit ' . $question->label . ' — ' . $assignment->name); ?>
<?php $__env->startSection('heading', 'Edit Question'); ?>
<?php $__env->startSection('breadcrumbs'); ?>
    <a href="<?php echo e(route('dashboard')); ?>" class="hover:text-gray-800 transition-colors">Dashboard</a>
    <span class="text-gray-300 mx-0.5">›</span>
    <a href="<?php echo e(route('qualifications.index')); ?>" class="hover:text-gray-800 transition-colors">Qualifications</a>
    <span class="text-gray-300 mx-0.5">›</span>
    <a href="<?php echo e(route('qualifications.show', $qualification)); ?>" class="hover:text-gray-800 transition-colors"><?php echo e($qualification->name); ?></a>
    <span class="text-gray-300 mx-0.5">›</span>
    <a href="<?php echo e(route('qualifications.assignments.index', $qualification)); ?>" class="hover:text-gray-800 transition-colors">Assignments</a>
    <span class="text-gray-300 mx-0.5">›</span>
    <a href="<?php echo e(route('qualifications.assignments.show', [$qualification, $assignment])); ?>" class="hover:text-gray-800 transition-colors"><?php echo e($assignment->name); ?></a>
    <span class="text-gray-300 mx-0.5">›</span>
    <span class="text-gray-700 font-medium">Edit <?php echo e($question->label ?: 'Question'); ?></span>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('page-actions'); ?>
    <a href="<?php echo e(route('qualifications.assignments.show', [$qualification, $assignment])); ?>"
        class="inline-flex items-center gap-2 px-4 py-2 border border-gray-300 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-50">
        ← Back
    </a>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>
<div class="max-w-2xl mt-2">
    <div class="bg-white rounded-xl border border-gray-200 p-6">
        
        <form id="update-form"
              method="POST"
              action="<?php echo e(route('qualifications.assignments.questions.update', [$qualification, $assignment, $question])); ?>"
              class="space-y-5">
            <?php echo csrf_field(); ?> <?php echo method_field('PUT'); ?>

            <?php if($errors->any()): ?>
                <div class="bg-red-50 border border-red-200 rounded-lg px-4 py-3 text-sm text-red-700 space-y-1">
                    <?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $e): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><p><?php echo e($e); ?></p><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </div>
            <?php endif; ?>

            <div class="grid grid-cols-2 gap-4">

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">
                        Question label
                        <span class="text-xs text-gray-400 font-normal">(optional)</span>
                    </label>
                    <input type="text" name="label" value="<?php echo e(old('label', $question->label)); ?>"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-orange-500"
                        placeholder="e.g. Q1, Q2a">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">
                        Marks <span class="text-red-500">*</span>
                    </label>
                    <input type="number" name="marks" value="<?php echo e(old('marks', $question->marks)); ?>" min="1" required
                        class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-orange-500">
                </div>

                <div class="col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">
                        Question text <span class="text-red-500">*</span>
                    </label>
                    <textarea name="question_text" rows="4" required
                        class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-orange-500"><?php echo e(old('question_text', $question->question_text)); ?></textarea>
                </div>

                <div class="col-span-2 pt-2 border-t border-gray-100">
                    <label class="block text-sm font-semibold text-gray-700 mb-1">
                        Expected answer / model memo
                        <span class="text-xs text-gray-400 font-normal">(optional)</span>
                    </label>
                    <p class="text-xs text-gray-500 mb-2">The correct or model answer for this question. The AI grader uses this as its marking anchor.</p>
                    <textarea name="expected_answer" rows="5"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-orange-500 font-mono"><?php echo e(old('expected_answer', $question->expected_answer)); ?></textarea>
                </div>

                <div class="col-span-2 pt-2 border-t border-gray-100">
                    <label class="block text-sm font-semibold text-gray-700 mb-1">
                        AI grading notes
                        <span class="text-xs text-gray-400 font-normal">(optional)</span>
                    </label>
                    <p class="text-xs text-gray-500 mb-2">Extra hints for the AI — key concepts to look for, common mistakes to flag, or how strictly to mark this question.</p>
                    <textarea name="ai_grading_notes" rows="3"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-orange-500"><?php echo e(old('ai_grading_notes', $question->ai_grading_notes)); ?></textarea>
                    <p class="text-xs text-gray-400 mt-1">Max 2 000 characters.</p>
                </div>

                <div class="col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Display order</label>
                    <input type="number" name="order" value="<?php echo e(old('order', $question->order)); ?>" min="0"
                        class="w-40 rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-orange-500">
                </div>

            </div>

            <div class="flex items-center gap-3 pt-2 border-t border-gray-100">
                <button type="submit"
                    class="px-5 py-2.5 hover:bg-[#e3b64d] hover:text-[#1e3a5f] text-white text-sm font-medium rounded-lg transition-colors bg-[#1e3a5f]">
                    Save Changes
                </button>
                <a href="<?php echo e(route('qualifications.assignments.show', [$qualification, $assignment])); ?>"
                    class="px-5 py-2.5 text-sm text-gray-600 hover:text-gray-900">Cancel</a>

                
                <button type="button"
                        onclick="document.getElementById('delete-form').submit()"
                        class="ml-auto px-4 py-2.5 text-sm text-red-600 hover:text-red-800 hover:bg-red-50 rounded-lg transition-colors"
                        onkeydown="if(event.key==='Enter'||event.key===' ')document.getElementById('delete-form').submit()">
                    Delete question
                </button>
            </div>
        </form>
    </div>
</div>


<form id="delete-form"
      method="POST"
      action="<?php echo e(route('qualifications.assignments.questions.destroy', [$qualification, $assignment, $question])); ?>"
      onsubmit="return confirm('Delete this question? This cannot be undone.')">
    <?php echo csrf_field(); ?> <?php echo method_field('DELETE'); ?>
</form>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /home/runner/workspace/ams/resources/views/questions/edit.blade.php ENDPATH**/ ?>