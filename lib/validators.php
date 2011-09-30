<?php

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


// TODO: RegexValidator, EmailValidator et al


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
    function clean($value) { return sizeof($value); }
    function compare($a,$b) { return $a<$b; }
}

class MaxLengthValidator extends BaseValidator {
    public $msg = 'Ensure this value has at most %1$d characters (it has %2$d).';
    public $code = 'max_length';
    function clean($value) { return sizeof($value); }
    function compare($a,$b) { return $a>$b; }
}

?>
