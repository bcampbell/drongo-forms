<?php

// use Dave Childs email validator rather than rolling our own regex magic
require_once('EmailAddressValidator.php');



// holds either a list of error messages as strings,
// or a single error, with identifying code and params
class ValidationError extends Exception {
    public $messages = array();
    public $code=null;      // which error it is
    public $params=null;    // params for that error
    function __construct( $msg, $code=null, $params=null ) {
        if(is_array($msg))
            // a bunch of error strings
            $this->messages = $msg;
        else {
            // a single message, with enough info for somewhere
            // higher up to customise it
            $this->messages = array($msg);
            $this->code=$code;
            $this->params=$params;
        }
    }
}


class RegexValidator {
    public $regex = '';
    public $message = 'Enter a valid value';
    public $code = 'invalid';

    function __construct($regex=null, $message=null, $code=null)
    {
        if(!is_null($regex)) $this->regex = $regex;
        if(!is_null($message)) $this->message = $message;
        if(!is_null($code)) $this->code = $code;
    }

    function execute($value) {
        if(!preg_match($this->regex,$value)) {
            throw new ValidationError($this->message, $this->code);
        }
    }
}



class URLValidator extends RegexValidator {
    // TODO: could add support for:
    //   verify_exists, which does an actual HTTP request to make sure url works
    //   IDN domains
    function __construct() {
        $regex = '&' .
            '^https?://' . /* http:// or https:// */
            '(?:(?:[A-Z0-9](?:[A-Z0-9-]{0,61}[A-Z0-9])?\.)+[A-Z]{2,6}\.?|' . /*domain... */
            'localhost|' . /* localhost... */
            '\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})' . /* ...or ip */
            '(?::\d+)?' . /* optional port */
            '(?:/?|[/?]\S+)$' .
            '&i';       /* case-insensitive */
        parent::__construct($regex, "Enter a valid URL", "invalid");
    }
}

class EmailValidator {
    public $message = 'Enter a valid e-mail address.';
    public $code = 'invalid';

    function __construct() {
        $this->v = new EmailAddressValidator;        
    }

    function execute($value) {
        if(!$this->v->check_email_address($value)) {
            throw new ValidationError($this->message, $this->code);
        }
    }
}    


class BaseValidator {

    function compare($a,$b) { return $a != $b; }
    function clean($value) { return $value; }
    public $msg = 'Ensure this value is %1$s (it is %2$s).';
    public $code = 'limit_value';

    function __construct($limit_value) { $this->limit_value = $limit_value; }

    // Could use __invoke() and make validator directly callable, but only on php5.3+
    function execute($value) {
        $cleaned = $this->clean($value);

        if($this->compare($cleaned, $this->limit_value)) {
            $params = array($this->limit_value, $cleaned);
            throw new ValidationError(vsprintf($this->msg,$params), $this->code, $params );
        }
    }
}

class MaxValueValidator extends BaseValidator {
    public $msg = 'Ensure this value is less than or equal to %1$s.';
    public $code = 'max_value';
    function compare($a,$b) { return $a>$b; }
}

class MinValueValidator extends BaseValidator {
    public $msg = 'Ensure this greater than or equal to %1$s.';
    public $code = 'min_value';
    function compare($a,$b) { return $a<$b; }
}


class MinLengthValidator extends BaseValidator {
    public $msg = 'Ensure this value has at least %1$d characters (it has %2$d).';
    public $code = 'min_length';
    function clean($value) { return strlen($value); }
    function compare($a,$b) { return $a<$b; }
}

class MaxLengthValidator extends BaseValidator {
    public $msg = 'Ensure this value has at most %1$d characters (it has %2$d).';
    public $code = 'max_length';
    function clean($value) { return strlen($value); }
    function compare($a,$b) { return $a>$b; }
}

?>
