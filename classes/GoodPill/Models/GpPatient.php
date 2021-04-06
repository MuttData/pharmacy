<?php

/**
 * Created by Reliese Model.
 */

namespace Goodpill\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;


/**
 * Class GpPatient
 *
 * @property int $patient_id_cp
 * @property int|null $patient_id_wc
 * @property string $first_name
 * @property string $last_name
 * @property Carbon $birth_date
 * @property string|null $patient_note
 * @property string|null $phone1
 * @property string|null $phone2
 * @property string|null $email
 * @property int|null $patient_autofill
 * @property string|null $pharmacy_name
 * @property string|null $pharmacy_npi
 * @property string|null $pharmacy_fax
 * @property string|null $pharmacy_phone
 * @property string|null $pharmacy_address
 * @property string|null $payment_card_type
 * @property string|null $payment_card_last4
 * @property Carbon|null $payment_card_date_expired
 * @property string|null $payment_method_default
 * @property string|null $payment_coupon
 * @property string|null $tracking_coupon
 * @property string|null $patient_address1
 * @property string|null $patient_address2
 * @property string|null $patient_city
 * @property string|null $patient_state
 * @property string|null $patient_zip
 * @property float|null $refills_used
 * @property string $language
 * @property string|null $allergies_none
 * @property string|null $allergies_cephalosporins
 * @property string|null $allergies_sulfa
 * @property string|null $allergies_aspirin
 * @property string|null $allergies_penicillin
 * @property string|null $allergies_erythromycin
 * @property string|null $allergies_codeine
 * @property string|null $allergies_nsaids
 * @property string|null $allergies_salicylates
 * @property string|null $allergies_azithromycin
 * @property string|null $allergies_amoxicillin
 * @property string|null $allergies_tetracycline
 * @property string|null $allergies_other
 * @property string|null $medications_other
 * @property Carbon $patient_date_added
 * @property Carbon|null $patient_date_registered
 * @property Carbon|null $patient_date_changed
 * @property Carbon $patient_date_updated
 * @property string|null $patient_inactive
 *
 * @package App\Models
 */
class GpPatient extends Model
{
    // Used the changable to track changes from the system
    use \GoodPill\Models\ChangeableTrait;

    /**
     * The Table for this data
     * @var string
     */
    protected $table = 'gp_patients';

    /**
     * The primary_key for this item
     * @var string
     */
    protected $primaryKey = 'patient_id_cp';

    /**
     * Does the database contining an incrementing field?
     * @var boolean
     */
    public $incrementing = false;

    /**
     * Does the database contining timestamp fields
     * @var boolean
     */
    public $timestamps = false;

    /**
     * Which database should the model use
     * @var string
     */
    protected $connection = 'mysql';

    /**
     * Fields that should be cast when they are set
     * @var array
     */
    protected $casts = [
        'patient_id_cp'    => 'int',
        'patient_id_wc'    => 'int',
        'patient_autofill' => 'int',
        'refills_used'     => 'float'
    ];

    /**
     * Fields that hold dates
     * @var array
     */
    protected $dates = [
        'birth_date',
        'payment_card_date_expired',
        'patient_date_added',
        'patient_date_registered',
        'patient_date_changed',
        'patient_date_updated'
    ];

    /**
     * Fields that represent database fields and
     * can be set via the fill method
     * @var array
     */
    protected $fillable = [
        'patient_id_wc',
        'first_name',
        'last_name',
        'birth_date',
        'patient_note',
        'phone1',
        'phone2',
        'email',
        'patient_autofill',
        'pharmacy_name',
        'pharmacy_npi',
        'pharmacy_fax',
        'pharmacy_phone',
        'pharmacy_address',
        'payment_card_type',
        'payment_card_last4',
        'payment_card_date_expired',
        'payment_method_default',
        'payment_coupon',
        'tracking_coupon',
        'patient_address1',
        'patient_address2',
        'patient_city',
        'patient_state',
        'patient_zip',
        'refills_used',
        'language',
        'allergies_none',
        'allergies_cephalosporins',
        'allergies_sulfa',
        'allergies_aspirin',
        'allergies_penicillin',
        'allergies_erythromycin',
        'allergies_codeine',
        'allergies_nsaids',
        'allergies_salicylates',
        'allergies_azithromycin',
        'allergies_amoxicillin',
        'allergies_tetracycline',
        'allergies_other',
        'medications_other',
        'patient_date_added',
        'patient_date_registered',
        'patient_date_changed',
        'patient_date_updated',
        'patient_inactive'
    ];

    /**
     * Test to see if the patient has both wc and cp ids
     * @return boolean
     */
    public function isMatched()
    {
        return ($this->exists
                && !empty($this->patient_id_cp)
                && !empty($this->patient_id_wc));
    }
}
