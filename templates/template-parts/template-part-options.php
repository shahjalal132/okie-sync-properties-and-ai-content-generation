<?php

$option3 = get_option( 'option3', '' );
$option4 = get_option( 'option4', '' );
$option5 = get_option( 'option5', '' );

?>

<h4 class="common-title">Options</h4>

<div class="options-wrapper">
    <div class="common-input-group">
        <label for="option1" title="How many description will be generate at a time? max limit 100">Limit ?</label>
        <input type="number" max="100" class="common-form-input" name="option1" id="option1" placeholder="Limit"
            value="<?= $option1 ?>">
    </div>
    <div class="common-input-group mt-20">
        <label for="option2">Title rewrite instruction</label>
        <input type="text" class="common-form-input" name="option2" id="option2" placeholder="Title rewrite instruction"
            value="<?= $option2 ?>">
    </div>
    <div class="common-input-group mt-20">
        <label for="option2">Page Title rewrite instruction</label>
        <input type="text" class="common-form-input" name="option3" id="option3"
            placeholder="Page Title rewrite instruction" value="<?= $option3 ?>">
    </div>
    <div class="common-input-group mt-20">
        <label for="option2">Meta description rewrite instruction</label>
        <input type="text" class="common-form-input" name="option4" id="option4"
            placeholder="Meta description rewrite instruction" value="<?= $option4 ?>">
    </div>
    <div class="common-input-group mt-20">
        <label for="option2">Description rewrite instruction</label>
        <input type="text" class="common-form-input" name="option5" id="option5"
            placeholder="Description rewrite instruction" value="<?= $option5 ?>">
    </div>

    <button type="button" class="save-btn mt-20 button-flex" id="save_options">
        <span>Save</span>
        <span class="spinner-loader-wrapper"></span>
    </button>
</div>