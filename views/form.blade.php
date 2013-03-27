<?php echo Form::open(); echo Form::token(); ?>
<?php foreach ($data as $k => $v) { echo Form::hidden($k,$v); } ?>
<?php echo Form::submit(); ?>
<?php echo Form::close(); ?>