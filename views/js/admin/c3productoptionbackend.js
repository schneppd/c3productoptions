/* C3productoptions module repeate actions */
function C3_registerProductOptionBackendActions(){
	var msg = $('.module_confirmation.alert.alert-success');
	if(msg.length) {
		var msgText = msg.text();
		if(msgText.indexOf("must repeat action") != -1) {
			var startAction = msgText.indexOf("must repeat action");
			var startActionId = msgText.indexOf("[", startAction) + 1;
			var endActionId = msgText.indexOf("]", startActionId);
			var actionId = msgText.slice(startActionId, endActionId); 
			$("#C3PRODUCTOPTIONS_ACTION").val(actionId);
			console.log("action repeate");
			$("#module_form_submit_btn").click();
		}
	}
}

$(document).ready(C3_registerProductOptionBackendActions);
