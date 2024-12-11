<?php echo $header; ?><?php echo $column_left; ?>

<div id="content">

    <div class="page-header">
        <div class="container-fluid">
            <div class="pull-right">
                <?php if( !isset($license_error) ) { ?>
                <button type="submit" name="action" value="save" form="form" data-toggle="tooltip" title="<?php echo $button_save; ?>" class="btn btn-primary"><i class="fa fa-save"></i> <?php echo $button_save; ?></button>
                <button type="submit" name="action" value="save_and_close" form="form" data-toggle="tooltip" title="<?php echo $button_save_and_close; ?>" class="btn btn-default"><i class="fa fa-save"></i> <?php echo $button_save_and_close; ?></button>
                <?php } else { ?>
                <a href="<?php echo $recheck; ?>" data-toggle="tooltip" title="<?php echo $button_recheck; ?>"class="btn btn-primary" /><i class="fa fa-check"></i> <?php echo $button_recheck; ?></a>
                <?php } ?>
                <a href="<?php echo $close; ?>" data-toggle="tooltip" title="<?php echo $button_close; ?>" class="btn btn-default"><i class="fa fa-close"></i> <?php echo $button_close; ?></a>
            </div>

            <h1><?php echo $heading_title_raw; ?></h1>

            <ul class="breadcrumb">
                <?php foreach ($breadcrumbs as $breadcrumb) { ?>
                <li><a href="<?php echo $breadcrumb['href']; ?>"><?php echo $breadcrumb['text']; ?></a></li>
                <?php } ?>
            </ul>

        </div>
    </div>

    <div class="container-fluid">

        <?php if ($error_warning) { ?>
        <div class="alert alert-danger">
            <i class="fa fa-exclamation-circle"></i> <?php echo $error_warning; ?>
            <button type="button" class="close" data-dismiss="alert">&times;</button>
        </div>
        <?php } ?>

        <?php if (isset($success) && $success) { ?>
        <div class="alert alert-success">
            <i class="fa fa-check-circle"></i>
            <?php echo $success; ?>
            <button type="button" class="close" data-dismiss="alert">&times;</button>
        </div>
        <?php } ?>

        <div class="panel panel-default">
            <div class="panel-body">

                <ul class="nav nav-tabs">

                    <li class="active"><a href="#tab-general" data-toggle="tab"><?php echo $tab_general; ?></a></li>
                    <li><a href="#tab-relation-payment" data-toggle="tab"><?php echo $tab_relation_payment; ?></a></li>
                    <li><a href="#tab-payment-methods" data-toggle="tab"><?php echo $tab_payment_methods; ?></a></li>
                    <li><a href="#tab-delivery-types" data-toggle="tab"><?php echo $tab_delivery_types; ?></a></li>
                    <li><a href="#tab-relation-order-statuses" data-toggle="tab"><?php echo $tab_relation_order_statuses; ?></a></li>
                    <li><a href="#tab-order-statuses" data-toggle="tab"><?php echo $tab_order_statuses; ?></a></li>
                    <li><a href="#tab-logs" data-toggle="tab"><?php echo $tab_logs; ?></a></li>
                </ul>

                <form action="<?php echo $save; ?>" method="post" enctype="multipart/form-data" id="form">
                    <div class="tab-content">
                        <div class="tab-pane active" id="tab-general">
                            <?php $widgets->dropdown('status',array( 0 => $text_disabled, 1 => $text_enabled)); ?>
                            <?php $widgets->input('auth_id'); ?>
                            <?php $widgets->input('auth_key'); ?>
                            <?php $widgets->input('auth_token'); ?>
                            <?php $widgets->input('shop_id'); ?>
                            <?php $widgets->dropdown('business_relationship',$type_business_relationship); ?>
                            <?php $widgets->checklist('invoice_statuses', $arr_order_statuses); ?>
                            <?php $widgets->checklist('countries_need_state', $countries); ?>
                            <?php $widgets->checklist('change_order_status', $isklad_order_statuses_arr); ?>
                        </div>

                        <div class="tab-pane" id="tab-logs">
                            <?php $widgets->debug_download_logs('debug',array( 0 => $text_disabled, 1 => $text_enabled), $clear, $download, $button_clear_log, $button_download_log); ?>
                            <textarea style="width: 100%; height: 300px; padding: 5px; border: 1px solid #CCCCCC; background: #FFFFFF; overflow: scroll;"><?php echo $logs; ?></textarea>
                        </div>

                        <div class="tab-pane" id="tab-relation-payment">
                            <?php if($payment_methods){ ?>
                            <table class="table table-bordered table-hover">
                                <thead>
                                <tr>
                                    <th>Payment method site</th>
                                    <th>Payment method site status</th>
                                    <th>Payment method isklad</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php foreach($payment_methods as $method){ ?>
                                <tr<?php if($method['status'] == 0){?> style="background:#f851517a" <?php } ?>>
                                    <td><?php echo $method['name']?></td>
                                    <td><?php echo $method['status'] ? 'ON' : 'OFF'?></td>
                                    <td>
                                        <select name="isklad_relation_payment_methods[<?php echo $method['code']; ?>]" class="form-control">
                                        <?php foreach($isklad_payment_methods as $isklad_method){ ?>
                                            <option value="<?php echo $isklad_method['ID']?>" <?php if(isset($isklad_relation_payment_methods[$method['code']]) && $isklad_relation_payment_methods[$method['code']]==$isklad_method['ID']){ ?>selected="selected"<?php }?>><?php echo $isklad_method['NAME_EN']?></option>
                                        <?php } ?>
                                        </select>
                                    </td>
                                </tr>
                                <?php } ?>
                                <tbody>
                            </table>
                            <?php } ?>
                        </div>

                        <div class="tab-pane" id="tab-payment-methods">
                            <?php if($isklad_payment_methods){ ?>
                            <table class="table table-bordered table-hover">
                                <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>NAME</th>
                                    <th>NAME_EN</th>
                                    <th>IS_PAID</th>
                                    <th>IS_CARD</th>
                                    <th>IS_COD</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php foreach($isklad_payment_methods as $method){ ?>
                                <tr>
                                    <td><?php echo $method['ID']?></td>
                                    <td><?php echo $method['NAME']?></td>
                                    <td><?php echo $method['NAME_EN']?></td>
                                    <td><?php echo $method['IS_PAID']?></td>
                                    <td><?php echo $method['IS_CARD']?></td>
                                    <td><?php echo $method['IS_COD']?></td>
                                </tr>
                                <?php } ?>
                                <tbody>
                            </table>
                            <?php } ?>
                        </div>

                        <div class="tab-pane" id="tab-delivery-types">
                            <?php if($delivery_types){ ?>
                            <table class="table table-bordered table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>IMAGE</th>
                                        <th>NAME</th>
                                        <th>TRANSFER_TYPE_NAME</th>
                                        <th>COD_AVAILABILITY</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($delivery_types as $type){ ?>
                                    <tr<?php if($type['COD_AVAILABILITY'] == 0){?> style="background:#f851517a" <?php } ?>>
                                        <td><?php echo $type['ID']?></td>
                                        <td>
                                            <image src="<?php echo $type['IMAGE']?>" width="100"/>
                                        </td>
                                        <td><?php echo $type['NAME']?></td>
                                        <td><?php echo $type['TRANSFER_TYPE_NAME']?></td>
                                        <td><?php echo $type['COD_AVAILABILITY']?></td>
                                    </tr>
                                    <?php } ?>
                                <tbody>
                            </table>
                            <?php } ?>
                        </div>

                        <div class="tab-pane" id="tab-relation-order-statuses">
                            <?php if($order_statuses){ ?>
                            <table class="table table-bordered table-hover">
                                <thead>
                                <tr>
                                    <th>Order status site</th>
                                    <th>Order status isklad</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php foreach($order_statuses as $order_status){ ?>
                                <tr>
                                <td><?php echo $order_status['name']?></td>
                                <td>
                                    <select name="isklad_relation_order_statuses[<?php echo $order_status['order_status_id']; ?>]" class="form-control">
                                        <?php foreach($isklad_order_statuses as $isklad_order_status){ ?>
                                        <option value="<?php echo $isklad_order_status['ID']?>" <?php if(isset($isklad_relation_order_statuses[$order_status['order_status_id']]) && $isklad_relation_order_statuses[$order_status['order_status_id']]==$isklad_order_status['ID']){ ?>selected="selected"<?php }?>><?php echo $isklad_order_status['NAME_EN']?></option>
                                        <?php } ?>
                                    </select>
                                </td>
                                </tr>
                                <?php } ?>
                                <tbody>
                            </table>
                            <?php } ?>
                        </div>

                        <div class="tab-pane" id="tab-order-statuses">
                            <?php if($isklad_order_statuses){ ?>
                            <table class="table table-bordered table-hover">
                                <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>NAME</th>
                                    <th>NAME_EN</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php foreach($isklad_order_statuses as $method){ ?>
                                <tr>
                                    <td><?php echo $method['ID']?></td>
                                    <td><?php echo $method['NAME']?></td>
                                    <td><?php echo $method['NAME_EN']?></td>
                                </tr>
                                <?php } ?>
                                <tbody>
                            </table>
                            <?php } ?>
                        </div>

                    </div>

                </form>

            </div>

        </div>

    </div>
</div>

<script type="text/javascript"><!--
	if (window.location.hash.indexOf('#tab') == 0 && $("[href=" + window.location.hash + "]").length) {
		$(".panel-body > .nav-tabs li").removeClass("active");
		$("[href=" + window.location.hash + "]").parents('li').addClass("active");
		$(".panel-body:first .tab-content:first .tab-pane:first").removeClass("active");
		$(window.location.hash).addClass("active");
	}
	$(".nav-tabs li a").click(function () {
		var url = $(this).prop('href');
		window.location.hash = url.substring(url.indexOf('#'));
	});

	// Специальный фикс системной функции, поскольку даниель понятия не имеет о том что в url может быть еще и hash
	// и по итогу этот hash становится частью token
	function getURLVar(key) {
		var value = [];

		var url = String(document.location);
		if( url.indexOf('#') != -1 ) {
			url = url.substring(0, url.indexOf('#'));
		}
		var query = url.split('?');

		if (query[1]) {
			var part = query[1].split('&');

			for (i = 0; i < part.length; i++) {
				var data = part[i].split('=');

				if (data[0] && data[1]) {
					value[data[0]] = data[1];
				}
			}

			if (value[key]) {
				return value[key];
			} else {
				return '';
			}
		}
	}
	//--></script>
<?php echo $footer; ?>
