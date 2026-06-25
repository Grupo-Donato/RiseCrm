<?php
if ($items) {
    foreach ($items as $item) {
?>
        <div class="col-md-3 col-sm-6">
            <div class="card">
                <div class="card-body p0">
                    <div class="cart-grid-item">
                        <div class="cart-grid-item-image-container">
                            <div class="cart-grid-item-image" style="background-image: url(<?php echo get_store_item_image($item->files); ?>)">
                            </div>
                        </div>
                        <div class="cart-grid-item-buttons">
                            <div class="add-to-cart-btn">
                                <?php
                                if (isset($item->added_to_cart) && $item->added_to_cart) {
                                    echo js_anchor("<i data-feather='shopping-bag' class='icon-16'></i> " . app_lang("added_to_cart"), array("class" => "btn btn-info text-white", "data-item_id" => $item->id, "disabled" => "disabled"));
                                } else {
                                    echo js_anchor("<i data-feather='shopping-cart' class='icon-16'></i> " . app_lang("add_to_cart"), array("class" => "btn btn-info text-white item-add-to-cart-btn", "data-item_id" => $item->id));
                                }
                                ?>
                            </div>
                            <div class="cart-item-details-btn ml10">
                                <?php echo modal_anchor(get_uri("store/item_view"), "<span class='view-item-details-link-btn btn btn-default'><i data-feather='eye' class='icon-16'></i></span>", array("data-modal-title" => app_lang("item_details"), "data-post-id" => $item->id)); ?>
                            </div>
                        </div>
                        <div class="cart-grid-item-details-section p15">
                            <div class="font-16 text-wrap-ellipsis strong"><?php echo $item->title; ?></div>
                            <div class="mt5 cart-item-rate">
                                <span class="text-danger strong"><?php echo to_currency($item->rate); ?></span><span class="text-off font-11"><?php echo $item->unit_type ? "/" . $item->unit_type : ""; ?></span>
                            </div>
                            <div class="text-wrap-ellipsis mt5"><?php echo $item->description ? process_images_from_content($item->description) : "-"; ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php
    }
    ?>

    <div class="text-center">
        <?php
        if ($result_remaining > 0) {
            echo ajax_anchor(get_uri("store/index/" . $next_page_offset . "/20/" . $category_id . "/" . $search), app_lang("load_more"), array("class" => "btn btn-default mt15 mb15 round pl15 spinning-btn", "title" => app_lang("load_more"), "data-inline-loader" => "1", "data-closest-target" => "#items-container", "data-append" => true));
        }
        ?>
    </div>

<?php
} else {
?>
    <div class="text-center box" style="height: 400px;">
        <div class="box-content" style="vertical-align: middle">
            <div class="mb15"><?php echo app_lang("item_empty_message"); ?></div>
            <span data-feather="frown" height="8rem" width="8rem" style="color:#d8d8d8"></span>
        </div>
    </div>
<?php } ?>