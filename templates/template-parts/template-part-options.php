<h4 class="common-title">Options</h4>

<div class="options-wrapper">
    <div class="common-input-group">
        <label for="option1" title="How many description will be generate at a time? max limit 100">Limit ?</label>
        <input type="number" max="100" class="common-form-input" name="option1" id="option1" placeholder="Limit"
            value="<?= $option1 ?>">
    </div>
    <div class="common-input-group mt-20" style="display: none;">
        <label for="option2">Option2</label>
        <input type="text" class="common-form-input" name="option2" id="option2" placeholder="Option2"
            value="<?= $option2 ?>">
    </div>

    <button type="button" class="save-btn mt-20 button-flex" id="save_options">
        <span>Save</span>
        <span class="spinner-loader-wrapper"></span>
    </button>
</div>