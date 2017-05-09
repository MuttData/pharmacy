var medicationGsheet = "https://spreadsheets.google.com/feeds/list/1MV5mq6605X7U1Np2fpwZ1RHkaCpjsb7YqieLQsEQK88/ovrg94l/public/values?alt=json"
var pharmacyGsheet  = "https://spreadsheets.google.com/feeds/list/11Ew_naOBwFihUrkaQnqVTn_3rEx6eAwMvGzksVTv_10/1/public/values?alt=json"
//ovrg94l is the worksheet id.  To get this you have to use https://spreadsheets.google.com/feeds/worksheets/1MV5mq6605X7U1Np2fpwZ1RHkaCpjsb7YqieLQsEQK88/private/full
var medications
var pharmacies

Cognito.load("forms", { id: "17" }, {success:load})


function load() {
  ExoJQuery(function() {
    showAcceptTerms()

    ExoJQuery(document).on('afterNavigate.cognito', navigate)

    ExoJQuery.ajax({
      url:medicationGsheet,
      type: 'GET',
      cache:true,
      success:function($data) {
        console.log('medications gsheet', $data.feed.entry)
        medications = $data.feed.entry.map(medication2select)
      }
    })

    ExoJQuery.ajax({
      url:pharmacyGsheet,
      type: 'GET',
      cache:true,
      success:function($data) {
        pharmacies = $data.feed.entry.map(pharmacy2select)
      }
    })
  })
}

function navigate(e, data) {
  ExoJQuery('form.donately-donation-form').prop('style', 'display:block !important')
  ExoJQuery('#donately-amount').prop('type', false).prop('disabled', true)
  upgradeMedication()
  upgradePharmacy()
}

function upgradeMedication() {
  console.log('upgradeMedication')
  var medicationSelect = jQuery('[data-field="MedicationSelect"] select')
  var medicationPrice  = jQuery('[data-field="MedicationPrice"] select')
  var medicationList   = jQuery('[data-field="MedicationList"] input')

  medicationSelect.children().remove() //otherwise blank option selected by default
  medicationSelect.select2({multiple:true,data:medications}).on("change", updatePrice)

  function updatePrice(e) {

    var price = medicationSelect.select2('data').reduce(sum, 0)

    console.log('updatePrice', price)
    //We have to update a text box because cognito won't save values from a multi-select form
    //We could just upgrade a text box (rather than select) but that would require full select2 not lite
    medicationPrice.val(Math.min(100, price)).click().change()
    medicationList.val(medicationSelect.val()).click().change()
  }
}

function upgradePharmacy() {
  console.log('upgradePharmacy')
  var BackupPharmacy = jQuery('[data-field="BackupPharmacy"] input')
  var BackupPharmacySelect = jQuery('[data-field="BackupPharmacySelect"] select')
  var TransferPharmacy = jQuery('[data-field="TransferPharmacy"] input')
  var TransferPharmacySelect = jQuery('[data-field="TransferPharmacySelect"] select')

  var options = {data:pharmacies, matcher:matcher, minimumInputLength:3}
  BackupPharmacySelect.select2(options).on("change", updateBackupPharmacy)
  TransferPharmacySelect.select2(options).on("change", updateTransferPharmacy)

  function updateBackupPharmacy(e) {
    BackupPharmacy.val(BackupPharmacySelect.val()).click().change()
  }

  function updateTransferPharmacy(e) {
    TransferPharmacy.val(TransferPharmacySelect.val()).click().change()
  }
}

function sum(a, b) {
  return +b.price+a
}

function showAcceptTerms() {
  ExoJQuery('.loader').hide()
  ExoJQuery('#c-submit-button').parent().parent().prepend('<div style="font-size:12px; max-width:785px; margin-left:10px; margin-bottom:10px; padding-top:10px">By clicking Accept & Submit, I acknowledge receipt of the <a href="https://goodpill.org/npp" target="window">Notice of Privacy Practice</a>, accept Good Pill Pharmacy\'s Terms of Use, and certify that I am <a href="https://goodpill.org/patient-eligibility" target="window">eligible to receive</a> medication(s) donated under OCGA 31-8-300.</div>')
}


function medication2select(entry, i) {
  var price     = entry.gsx$day_2.$t || entry.gsx$day.$t
  var message   = []

  if (entry.gsx$supplylevel.$t)
    message.push(entry.gsx$supplylevel.$t)

  message.push(entry.gsx$day.$t ? '30 day' : '90 day')

  var drug = ' '+entry.gsx$drugname.$t+', '+price+' ('+message.join(', ')+')'
  return {id:drug, text:drug, disabled:entry.gsx$supplylevel.$t == 'Out of Stock', price:price.replace('$', '')}
}

function pharmacy2select(entry, i) {
  var address  = entry.gsx$cleanaddress.$t.replace(/(\d{5})(\d{4})/, '$1')
  var pharmacy = entry.gsx$name.$t+', '+address+', Phone: '+entry.gsx$phone.$t
  return {id:pharmacy+', Fax:'+entry.gsx$fax.$t, text:pharmacy}
}

//http://stackoverflow.com/questions/36591473/how-to-use-matcher-in-select2-js-v-4-0-0
function matcher(param, data) {
   if ( ! param.term ||  ! data.text) return null
   var has = true
   var words = param.term.toUpperCase().split(" ")
   var text  = data.text.toUpperCase()
   for (var i =0; i < words.length; i++)
     if ( ! ~ text.indexOf(words[i])) return null

   return data
}
