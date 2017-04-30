var gsheet = "https://spreadsheets.google.com/feeds/list/1MV5mq6605X7U1Np2fpwZ1RHkaCpjsb7YqieLQsEQK88/ovrg94l/public/values?alt=json"
//ovrg94l is the worksheet id.  To get this you have to use https://spreadsheets.google.com/feeds/worksheets/1MV5mq6605X7U1Np2fpwZ1RHkaCpjsb7YqieLQsEQK88/private/full
var medications

Cognito.load("forms", { id: "17" }, {success:function() {
  console.log('cognito success')
  setTimeout(load, 1000)
}})

jQuery.ajax({
   url:gsheet,
   type: 'GET',
   cache:true,
   success:function($data) {
     console.log('$data.feed.entry', $data.feed.entry)
     medications = $data.feed.entry.map(gsheet2select)
     load()
   }
})

//Removes conflict between enfold and select2
document.body.removeAttribute("id");

load.count = 0
function load() {
  console.log('load.count', load.count)
  if(++load.count < 2) return
  showAcceptTerms()
  upgradeMedication()
}

function upgradeMedication() {
  var medicationSelect = jQuery('[data-field="SearchAndSelectMedicationsByGenericName"] select')
  var medicationPrice  = jQuery('[data-field="MedicationPrice"] select')
  var medicationList   = jQuery('[data-field="MedicationList"] input')
console.log(1)
  medicationSelect.children().remove()
  console.log(2)
  medicationSelect.select2({multiple:true,data:medications})
  .on("select2:open", toggleHeader)
  .on("select2:close", toggleHeader)
  .on("change", updatePrice)

  function updatePrice(e) {
    console.log(3)
    var price = medicationSelect.select2('data').reduce(sum, 0)
  console.log(4)
    //We have to update a text box because cognito won't save values from a multi-select form
    medicationPrice.val(Math.min(100, price)).click().change()
      console.log(5)
    medicationList.val(medicationSelect.val()).click().change()
      console.log(6)
  }
}

function gsheet2select(entry, i) {
  var price     = entry.gsx$day_2.$t || entry.gsx$day.$t
  var message   = []

  if (entry.gsx$supplylevel.$t)
    message.push(entry.gsx$supplylevel.$t)

  if (entry.gsx$day.$t)
    message.push('30 day')

  message = message.length ? ' ('+message.join(', ')+')' : ''

  var drug = ' '+entry.gsx$drugname.$t+', $'+price+message
  var result = {id:drug, text:drug, disabled:entry.gsx$supplylevel.$t == 'Out of Stock', price:price}
  return result
}

function toggleHeader() {
  console.log('A')
  jQuery('header').toggle()
  console.log('B')
}

function sum(a, b) {
  return +b.price+a
}

function showAcceptTerms() {
  jQuery('#loading').hide()
  jQuery('.c-button-section').prepend('<div style="font-size:12px; max-width:785px; margin-left:10px; margin-bottom:10px">By clicking Accept & Submit, I attest to the statements below and understand that the medication(s) that I am receiving from SIRUM now & in the future may have been donated, previously dispensed, and potentially stored in an uncontrolled environment.</div>')
}
