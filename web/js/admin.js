$(document).ready(function(){
    $('textarea').autosize();    
});

$(document).delegate('#textbox', 'keydown', function(e) {
  var keyCode = e.keyCode || e.which;

  if (keyCode == 9) {
    e.preventDefault();
    var start = $(this).get(0).selectionStart;
    var end = $(this).get(0).selectionEnd;

    // set textarea value to: text before caret + tab + text after caret
    $(this).val($(this).val().substring(0, start)
                + "\t"
                + $(this).val().substring(end));

    // put caret at right position again
    $(this).get(0).selectionStart =
    $(this).get(0).selectionEnd = start + 1;
  }
});

$(".add").click(function(){
    $(".add_teacher").toggle();
});

$(".close").click(function() {
	$(".add_teacher").hide();
});

$("#enroll").submit(function() {
	
	if($("#Email").val() == '')
		$("#result").html("Please enter an email!");
	else {
		var url = location.origin+'/ajax/teacher';
	
		$("#loading").show();
		$("#submit").hide();
		$("#result").html("");
	
		$.ajax({
			type: "POST",
			url: url,
			dataType: 'json',
			data: $("#enroll").serialize(),
			success: function(response)
			{
				$("#loading").hide();
				$("#submit").show();
				var json = JSON.stringify(response);
				json = JSON.parse(json);
				if(json.status == 200) {
					$("#result").html(""+json.message);
					$("#Email").val('');
				}
				else if(json.status != 200) {
					$("#result").html(""+json.message);
				}
			}
		});
	}
	return false;
})

$("#add_problem").click(function(){
    $(".add_problem").toggle();
});

$("#close2").click(function() {
	$(".add_problem").hide();
});

$("#problem").submit(function() {
	
	if($("#Title").val() == '')
		$("#result2").html("Please enter a title.");
	else if($("#Prompt").val() == '')
		$("#result2").html("Please enter a prompt.");
	else if($("#Method").val() == '')
		$("#result2").html("Please enter the method.");
	else if($("#Test").val() == '')
		$("#result2").html("Please enter at least one test.");
	else if($("#Output").val() == '')
		$("#result2").html("Please enter expected output.");
	else {
	
		$("#loading2").show();
		$("#submit2").hide();
	
		var url = location.origin+'/ajax/problem';
	
		$.ajax({
			type: "POST",
			url: url,
			dataType: 'json',
			data: $("#problem").serialize(),
			success: function(response)
			{
				$("#loading2").hide();
				$("#submit2").show();
				var json = JSON.stringify(response);
				json = JSON.parse(json);
				if(json.status == 200) {
					$("#result2").html(""+json.message);
					$("#Title").val('');
					$("#Prompt").val('');
					$("#Method").val('');
					$("#Test").val('');
					$("#Output").val('');
				}
				else if(json.status != 200) {
					$("#result2").html(""+json.message);
				}
			}
		})
	}
	return false;
});

$("#close2").click(function() {
	$(".add_problem").hide();
});