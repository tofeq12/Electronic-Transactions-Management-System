
<?php $__currentLoopData = $itqan['trxTypes'] ?? []; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $t): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
  <?php if($t['fields'] !== []): ?>
  <div class="form-section-title dyn-fields-title" data-type="<?php echo e($t['slug']); ?>" hidden><?php echo e(__('بيانات إضافية')); ?> — <?php echo e($t['label']); ?></div>
  <div class="form-grid dyn-fields" data-type="<?php echo e($t['slug']); ?>" hidden>
    <?php $__currentLoopData = $t['fields']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $f): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
    <div class="field <?php echo e(in_array($f['type'], ['textarea', 'file'], true) ? 'field-full' : ''); ?>">
      <label for="df-<?php echo e($f['id']); ?>"><?php echo e($f['label']); ?><?php if($f['required']): ?> <span class="req-star">*</span><?php endif; ?></label>
      <?php switch($f['type']):
        case ('textarea'): ?>
          <textarea id="df-<?php echo e($f['id']); ?>" data-fid="<?php echo e($f['id']); ?>" <?php if($f['required']): ?> data-req="1" <?php endif; ?>></textarea>
          <?php break; ?>
        <?php case ('number'): ?>
          <input type="number" id="df-<?php echo e($f['id']); ?>" data-fid="<?php echo e($f['id']); ?>" <?php if($f['required']): ?> data-req="1" <?php endif; ?> <?php if($f['min'] !== null): ?> min="<?php echo e($f['min']); ?>" <?php endif; ?> <?php if($f['max'] !== null): ?> max="<?php echo e($f['max']); ?>" <?php endif; ?>>
          <?php break; ?>
        <?php case ('date'): ?>
          <input type="date" id="df-<?php echo e($f['id']); ?>" data-fid="<?php echo e($f['id']); ?>" <?php if($f['required']): ?> data-req="1" <?php endif; ?>>
          <?php break; ?>
        <?php case ('select'): ?>
          <select id="df-<?php echo e($f['id']); ?>" data-fid="<?php echo e($f['id']); ?>" <?php if($f['required']): ?> data-req="1" <?php endif; ?>>
            <option value=""><?php echo e(__('اختر...')); ?></option>
            <?php $__currentLoopData = $f['options']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $opt): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <option value="<?php echo e($opt); ?>"><?php echo e($opt); ?></option>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
          </select>
          <?php break; ?>
        <?php case ('checkbox'): ?>
          <label class="chk-wrap"><input type="checkbox" id="df-<?php echo e($f['id']); ?>" data-fid="<?php echo e($f['id']); ?>" <?php if($f['required']): ?> data-req="1" <?php endif; ?>> <span><?php echo e($f['label']); ?></span></label>
          <?php break; ?>
        <?php case ('file'): ?>
          
          <p class="dyn-file-hint"><?php echo e(__('يُرفق ضمن ملفات المعاملة')); ?></p>
          <?php break; ?>
        <?php default: ?>
          <input type="text" id="df-<?php echo e($f['id']); ?>" data-fid="<?php echo e($f['id']); ?>" <?php if($f['required']): ?> data-req="1" <?php endif; ?>>
      <?php endswitch; ?>
    </div>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
  </div>
  <?php endif; ?>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
<?php /**PATH C:\Users\Tofeq\Desktop\laravale\resources\views/partials/dyn-fields.blade.php ENDPATH**/ ?>