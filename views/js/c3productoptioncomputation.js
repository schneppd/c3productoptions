/* C3productoptions module client logic */
function C3_processProductOptionData(){
	//if prestashop old logic used, do not use this script
	if (!C3_isPrestashopOldAttributeLogicUsed()) {
		//get data
		var id_product = c3_product_options["id_product"];
		var product_base_price = parseFloat(c3_product_options["product_base_price"]);

		//insert into dom
		var tax = parseFloat(100.0 + taxRate) / 100.0;
		var buy_block = $("#buy_block");
		buy_block.attr('data-c3-id_product', id_product);
		buy_block.attr('data-c3-base_price', product_base_price * tax);
		buy_block.attr('data-c3-combination_data', "");
		//delete old dom from vanilla prestashop
		var product_attributes = $("#buy_block .product_attributes");
		var attributes = product_attributes.children("#attributes");
		attributes.empty();//delete old attribute logic, need to merge

		$("#availability_statut, #last_quantities").removeAttr('style');

		//todo add event when user add +/- 1 product
		$("#buy_block .product_attributes #quantity_wanted").on("change", C3_productOptionSelectionChanged);

		//process options data if exists and insert new data into dom
		if (c3_product_options["data"].length > 0) {
			$('<div class="clearfix"/>').appendTo(attributes);
			var div_options = $('<div id="product_options"/>')
			$.each(c3_product_options["data"], function (index_attribute_group) {
				var id_attribute_group = this["id_attribute_group"];
				var required_option = this["required_option"];
				var group_type = this["group_type"];
				var lbl_attribute_group = this["lbl_attribute_group"];

				var new_fieldset = $('<fieldset class="attribute_fieldset">');

				var class_attribute_group = 'attribute_label';
				var id_attribute_group_html = 'group_' + id_attribute_group;
				if (required_option)
					class_attribute_group += ' c3_required_option';
				var text_label = lbl_attribute_group;

				var attribute_container = $('<div class="attribute">');
				var attribute_element = null;
				if (group_type == "text")
					attribute_element = $('<input type="text" class="form-control attribute_text no-print">');
				else if (group_type == "select")
					attribute_element = $('<select class="form-control attribute_select no-print">');
				else if (group_type == "checkbox")
					attribute_element = $('<input type="checkbox" class="form-control attribute_checkbox no-print">');

				attribute_element.attr('name', id_attribute_group_html);
				attribute_element.attr('id', id_attribute_group_html);
				if (required_option)
					attribute_element.addClass('c3_required_option');

				$.each(this["data"], function (index_attribute) {
					var id_attribute = this["id_attribute"];
					var price_attribute = parseFloat(this["price_attribute"]) * tax;
					var lbl_attribute = this["lbl_attribute"];

					if ((group_type == "text" || group_type == "checkbox") && price_attribute > 0)
						text_label += ' (+' + price_attribute + currencySign + ')';

					if (group_type == "select") {
						var lbl_option = lbl_attribute;
						if (price_attribute > 0)
							lbl_option += ' (+' + price_attribute + currencySign + ')';

						var option = $('<option title="' + lbl_option + '">' + lbl_option + '</option>');

						option.attr('value', id_attribute);
						option.attr('data-c3-price_attribute', price_attribute);
						option.attr('data-c3-id_attribute', id_attribute);
						if (index_attribute == 0)
							option.attr('selected', 'selected');

						option.appendTo(attribute_element);
					} else {
						attribute_element.attr('data-c3-id_attribute', id_attribute);
						attribute_element.attr('data-c3-price_attribute', price_attribute);
					}
				});

				attribute_element.on("change", C3_productOptionSelectionChanged);

				attribute_element.appendTo(attribute_container);
				var new_label = $('<label class="' + class_attribute_group + '" for="' + id_attribute_group_html + '">' + text_label + '</label>');
				new_label.appendTo(new_fieldset);
				attribute_container.appendTo(new_fieldset);
				new_fieldset.appendTo(div_options);
			});
			product_attributes.prepend(div_options);
		}

		//when user change quantity, calculate price and get availability
		// The button to increment the product value
		$(document).on('click', '.product_quantity_up', function (e) {
			e.preventDefault();
			fieldName = $(this).data('field-qty');
			var currentVal = parseInt($('#quantity_wanted').val());
			var quantityAvailableCap = 500;
			if (quantityAvailable > 0)
				quantityAvailableCap = quantityAvailable;
<<<<<<< HEAD
			
			if (!isNaN(currentVal) && currentVal < quantityAvailableCap)
				$('#quantity_wanted').val(currentVal + 1);
			else
				$('#quantity_wanted').val(quantityAvailableCap);
=======
>>>>>>> 8c5070a3eda62db831936abc8f4ac9308237c729

			C3_productOptionSelectionChanged();
		});
		// The button to decrement the product value
		$(document).on('click', '.product_quantity_down', function (e) {
			e.preventDefault();//prevent default theme computations
			fieldName = $(this).data('field-qty');
			var currentVal = parseInt($('#quantity_wanted').val());
<<<<<<< HEAD
			
			if (!isNaN(currentVal) && currentVal > 1)
				$('#quantity_wanted').val(currentVal - 1).trigger('keyup');
			else
				$('#quantity_wanted').val(1);
=======
>>>>>>> 8c5070a3eda62db831936abc8f4ac9308237c729

			C3_productOptionSelectionChanged();
		});
		//check availability / price for selected value when page has finished to load
		C3_productOptionSelectionChanged();
	}

}
/*
*	if old logic is used, this script will be disabled
 */
function C3_isPrestashopOldAttributeLogicUsed(){
	if (typeof combinations == 'undefined')
		return false;
	if(combinations.length == 0)
		return false;
	return true;
}

function C3_productOptionSelectionChanged(){
	C3_updateProductPriceWithSelection();
	C3_showProductSelectionIsAvailable();
}

function C3_updateProductPriceWithSelection(){
	var buy_block = $("#buy_block");
	var product_base_price = parseFloat(buy_block.attr("data-c3-base_price"));

	var quantity = 1 * $("#buy_block .product_attributes #quantity_wanted").val();
	if(quantity > quantityAvailable){
		$("#buy_block .product_attributes #quantity_wanted").val(quantityAvailable);
		quantity = quantityAvailable;
	}

	var selected_elements = $("#buy_block .product_attributes #product_options input:text");
	$.each(selected_elements, function(){
		if($(this).val())//if user gave value
			product_base_price += parseFloat($(this).attr('data-c3-price_attribute'));
	});

	var selected_elements = $("#buy_block .product_attributes #product_options input:checkbox:checked, #buy_block .product_attributes #product_options select option:selected");
	$.each(selected_elements, function(){
		if($(this).val())
			product_base_price += parseFloat($(this).attr('data-c3-price_attribute'));
	});

	product_base_price *= quantity;
	var finalPrice = parseFloat(product_base_price).toFixed(2);
	$("#our_price_display").text(finalPrice + ' ' + currencySign);
}

function C3_showProductSelectionIsAvailable(){
	var quantity = 1 * $("#buy_block .product_attributes #quantity_wanted").val();
	$("#add_to_cart, #pQuantityAvailable, #availability_statut, #last_quantities").addClass('hide');
	var id_product = c3_product_options["id_product"];
	var combinationData = "";
	var combinationBuyData = "";

	var selected_elements = $("#buy_block .product_attributes #product_options input:text, #buy_block .product_attributes #product_options input:checkbox:checked");
	$.each(selected_elements, function(){
		if(($(this).attr('type') == "text" && $(this).val()) || $(this).attr('type') == "checkbox"){//if user gave value
			id_attribute = $(this).attr('data-c3-id_attribute');
			id_attribute_group = $(this).attr('id').replace("group_", "");
			combinationData += "_" + id_attribute_group + "-" + id_attribute;
			if($(this).attr('type') == "text" && $(this).val())
				combinationBuyData += "_" + id_attribute_group + "-" + id_attribute + "[[[" + $(this).val() + "]]]";
			else
				combinationBuyData += "_" + id_attribute_group + "-" + id_attribute;
		}
	});
	var selected_elements = $("#buy_block .product_attributes #product_options select option:selected");
	$.each(selected_elements, function(){
		id_attribute = $(this).attr('data-c3-id_attribute');
		id_attribute_group = $(this).parent().attr('id').replace("group_", "");
		combinationData += "_" + id_attribute_group + "-" + id_attribute;
		combinationBuyData += "_" + id_attribute_group + "-" + id_attribute;
	});
	combinationData = C3_removeFirstCharacter(combinationData);
	combinationBuyData = C3_removeFirstCharacter(combinationBuyData);
	$("#buy_block").attr('data-c3-combination_data', combinationBuyData);

 	if(!C3_isRequiredOptionsFilled()){
		if(c3_product_options["data"].length > 0){
			$("#availability_value").text(doesntExist);
			$("#availability_statut").removeClass('hide');
		}
		else{
			$("#availability_value").text(doesntExistNoMore);
			$("#availability_statut").removeClass('hide');
		}
		return;
	}

	var query = $.ajax({
		type: 'POST'
		,url: baseDir + 'c3productoptioncheck' + '?rand=' + Math.floor((Math.random() * 1000) + 500) + new Date().getTime() + Math.floor((Math.random() * 1000) + 500)
		,data: {
			action: 'c3checkselectionavailability'
			,ajax: true
			,quantity: quantity
			,id_product: id_product
			,combination_data: combinationData
		}
		,dataType: 'json'
		,success: function(json) {
			if(json['available']){
				$("#add_to_cart").removeClass('hide');
				$("#add_to_cart").removeAttr('style');//disable default theme hide
				var txt_availability = (availableNowValue == '')? 'disponible':availableNowValue;
				$("#availability_statut #availability_value").text(txt_availability);
				$("#availability_statut").removeClass('hide');
			}
			else{
				if(c3_product_options["data"].length > 0){
					$("#availability_value").text(doesntExist);
					$("#availability_statut").removeClass('hide');
				}
				else{
					$("#availability_value").text(doesntExistNoMore);
					$("#availability_statut").removeClass('hide');
				}
			}
		}
		,error: function(xhr, status, error) {
			//show error message
			window.alert(error);
		}
	});

	if($("#add_to_cart").is(':animated'))
		$("#add_to_cart").stop().animate({opacity:'100'});//disable default theme function

	if($("#availability_statut").is(':animated'))
		$("#availability_statut").stop().animate({opacity:'100'});//disable default theme function
	if($("#last_quantities").is(':animated'))
		$("#last_quantities").stop().animate({opacity:'100'});//disable default theme function
}

function C3_isRequiredOptionsFilled(){
	var res = true;
	var missing_elements = $("#buy_block .product_attributes #product_options .c3_missing_option");
	$.each(missing_elements, function(){
		$(this).removeClass('c3_missing_option');
	});
	var selected_elements = $("#buy_block .product_attributes #product_options .c3_required_option");
	$.each(selected_elements, function(){
		if($(this).attr('type') == "text" && !$(this).val()) {
			$(this).parent().parent().addClass('c3_missing_option');
			res = false;
		}
		if($(this).attr('type') == "checkbox" && !$(this).is(':checked')) {
			//if empty required input or non checked checkbox
			//$(this).parent().parent().parent().parent().children('label').addClass('c3_missing_option');
			$(this).parent().parent().addClass('c3_missing_option');
			res = false;
		}
		if($(this).attr('type') == "select" && !$(this).val()) {
			$(this).parent().parent().addClass('c3_missing_option');
			res = false;
		}
	});
	return res;
}

function C3_removeFirstCharacter(bad_string){
	//remove first unnecessary _
	return bad_string.substring(1);
}

$(document).ready(C3_processProductOptionData);
