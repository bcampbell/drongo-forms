<?php



/* helper class to specify (and collect and render) js and css */
class Media
{
    private $js=array();
    private $css=array();
    function __construct($data=null)
    {
        if(is_array($data)) {
            if(array_key_exists('js',$data))
                $this->js = $data['js'];
            if(array_key_exists('css',$data))
                $this->css = $data['css'];
        }
    }

    // add another media object to this one
    function extend($other)
    {
        $this->js = array_unique(array_merge($other->js, $this->js));
        foreach($other->css as $medium=>$v) {
            if(array_key_exists($medium,$this->css)) {
                $this->css[$medium] = array_unique(array_merge($v,$this->css[$medium]));
            } else {
                $this->css[$medium] = $v;
            }
        }
    }

    // return a string suitable for including in a <head> section of a page
    function render()
    {
        $out = implode("\n", array_merge($this->render_css(),$this->render_js()));
        if($out)
            $out .= "\n";
        return $out;
    }

    // return a list of <script> strings
    function render_js()
    {
        $out = array();
        foreach($this->js as $path) {
            $out[] = sprintf('<script type="text/javascript" src="%s"></script>', $path );
        }
        return $out;
    }

    // return a list of <link> strings
    function render_css()
    {
        $out = array();
        $mediums = array_keys($this->css);
        foreach($mediums as $medium) {
            foreach($this->css[$medium] as $path) {
                $out[] = sprintf('<link href="%s" type="text/css" media="%s" rel="stylesheet" />',$path,$medium);
            }
        }
        return $out;
    }
}




    /*
    Convert a array of attributes to a single string.
    The returned string will contain a leading space followed by key="value",
    XML-style pairs.  It is assumed that the keys do not need to be XML-escaped.
    If passed empty arrry or null, returns an empty string.
    */
function flatatt($attrs) {
    if(!$attrs) {
        return '';
    }

    $out = '';
    foreach($attrs as $k=>$v) {
        $out .= sprintf(' %s="%s"', $k, htmlspecialchars($v));
    }
    return $out;
}


abstract class Widget
{
    public $is_hidden = FALSE; // Determines whether this corresponds to an <input type="hidden">.
    public $needs_multipart_form = FALSE; // Determines does this widget need multipart-encrypted form

    public $attrs = array();
    public $media = null;

    function __construct($attrs=null) {
        if(!is_null($attrs))
            $this->attrs = $attrs;
        // should always have a valid media object, even if it's empty
        if(is_null($this->media)) {
            $this->media = new Media();
        }
    }

    /*
    Returns this Widget rendered as HTML

    The 'value' given is not guaranteed to be valid input, so subclass
    implementations should program defensively.
    */
    abstract function render($name,$value,$attrs=null);

    /* Helper function for building an attribute dictionary. */
    function build_attrs($extra_attrs) {
        return array_merge($this->attrs,$extra_attrs);
    }

    /*
    Given an array of data and this widget's name, returns the value
    of this widget. Returns null if it's not provided.
    (named value_from_datadict() in original python)
     */
    function value_from_data($data, $files, $name) {
        if( array_key_exists($name,$data) )
            return $data[$name];
        return null;
    }

    // Return True if data differs from initial.
    function _has_changed($initial, $data) {
        // For purposes of seeing whether something has changed, None is
        // the same as an empty string, if the data or inital value we get
        // is None, replace it with ''
        $data = $data ? $data : '';
        $initial = $initial ? $initial : '';
        if( $data!=$initial)
            return TRUE;
        return FALSE;
    }


    /*
        Returns the HTML ID attribute of this Widget for use by a <label>,
        given the ID of the field. Returns null if no ID is available.

        This hook is necessary because some widgets have multiple HTML
        elements and, thus, multiple IDs. In that case, this method should
        return an ID value that corresponds to the first ID in the widget's
        tags.
     */
    function id_for_label( $id ) {
        return $id;
    }
}


class Input extends Widget
{
    public $input_type = null;

    function render($name,$value,$attrs=null) {
        if(is_null($value))
            $value='';
        $attrs['type'] = $this->input_type;
        $attrs['name'] = $name;
        // Only add the 'value' attribute if a value is non-empty.
        if($value!='')
            $attrs['value'] = $value;

        $final_attrs = $this->build_attrs($attrs);
        return sprintf( "<input%s />", flatatt($final_attrs));
    }
}



class TextInput extends Input
{
    public $input_type='text';
}

class PasswordInput extends Input
{
    public $input_type = 'password';
    // TODO: support render_value flag in ctor and render()
}


class HiddenInput extends Input
{
    public $input_type='hidden';
    public $is_hidden=TRUE;
}


// TODO:
// MultipleHiddenInput
// FileInput

class Textarea extends Widget {
    function __construct($attrs=null) {
        /* The 'rows' and 'cols' attributes are required for HTML correctness. */
        $default_attrs = array('cols'=>'40', 'rows'=>'10');
        if($attrs) {
            $default_attrs = array_merge($default_attrs, $attrs);
        }
        parent::__construct($default_attrs);
    }

    function render($name, $value, $attrs=null) {
        if(is_null($value))
            $value='';
        $attrs['name']=$name;
        $final_attrs = $this->build_attrs($attrs);
        return sprintf( "<textarea%s>%s</textarea>", flatatt($final_attrs),htmlspecialchars($value));
    }
}

// TODO:
// DateInput
// DateTimeInput
// TimeInput



class CheckboxInput extends Widget {
    function __construct($attrs=null, $check_test=null) {
        parent::__construct($attrs);
        // check_test is a callable that takes a value and returns True
        // if the checkbox should be checked for that value.
        $this->check_test = $check_test;
    }

    function render($name, $value, $attrs=null) {
        if(is_null($attrs))
            $attrs=array();
        $attrs['type']='checkbox';
        $attrs['name'] = $name;
        $final_attrs = $this->build_attrs($attrs);
        $result = FALSE;
        if(is_callable($this->check_test)) {
            $result = call_user_func($this->check_test,$value);
        } else {
            $result = $value ? TRUE:FALSE;
        }
        if($result) {
            $final_attrs['checked'] = 'checked';
        }

        if($value!=='' && $value!==TRUE && $value!==FALSE && $value!==null) {
            // Only add the 'value' attribute if a value is non-empty.
            $final_attrs['value'] = $value;
        }

        return sprintf('<input%s />',flatatt($final_attrs));
    }

    function value_from_data($data, $files, $name) {
        if( !array_key_exists($name,$data)) {
            // A missing value means False because HTML form submission does not
            // send results for unselected checkboxes.
            return FALSE;
        }

        // translate strings "true" and "false" into bool.
        $value = $data[$name];
        $l = strtolower($value);
        if( $l==='true' )
            return TRUE;
        if( $l==='false' )
            return FALSE;
        return $value;
    }

    function _has_changed($initial, $data) {
        // Sometimes data or initial could be None or u'' which should be the
        // same thing as False.
        return (bool)$initial != (bool)$data;
    }
}

class Select extends Widget {
    function __construct($attrs=null, $choices=array()) {
        parent::__construct($attrs);
        $this->choices=$choices;
    }


    // TODO: should we even have $choices as a param here?
    // No keyword args in php... sigh...
    function render($name, $value, $attrs=null, $choices=array()) {
        if(is_null($value))
            $value='';
        $attrs['name']=$name;
        $final_attrs = $this->build_attrs($attrs);

        $output=array();
        $output[] = sprintf('<select%s>',flatatt($final_attrs));
        $output =array_merge($output, $this->render_options($choices,array($value)));
        $output[] = "</select>";

        return join("\n",$output );
    }

    function render_options($choices, $selected_choices) {
        $output=array();
        // awful trouble merging php arrays with numeric keys...
        // so just avoid issue for now.
        // TODO: fix.
        // $choices = array_merge($this->choices,$choices);
        if( $choices )
            throw new Exception( "Not Implemented" );
        $choices = $this->choices;

        foreach($choices as $option_value=>$option_label) {
            // TODO: handle optgroups
            $sel='';
            // gah - stupid php trainwreck. If one of the values is numeric, php
            // will force it to an int.
            // And in php, "foobar"==0 is true!
            // so force value to a string.
            if(in_array(strval($option_value),$selected_choices,true)) {
                $sel=' selected';
            }
            $output[]=sprintf('<option value="%s"%s>%s</option>',
                htmlspecialchars($option_value),
                $sel,
                htmlspecialchars($option_label) );
        }
        return $output;
    }
}


// TODO:
// NullBooleanSelect

class SelectMultiple extends Select {
    function render($name, $value, $attrs=null, $choices=array()) {
        if(is_null($value))
            $value=array();
        $attrs['name']=$name . '[]';
        $final_attrs = $this->build_attrs($attrs);

        $output=array();
        $output[] = sprintf('<select multiple="multiple"%s>',flatatt($final_attrs));
        $output =array_merge($output, $this->render_options($choices,$value));
        $output[] = "</select>";

        return join("\n",$output );
    }

    function _has_changed($initial, $data) {
        if(is_null($initial))
            $initial = array();
        if(is_null($data))
            $data = array();
        if(sizeof(array_diff($initial, $data)) > 0)
            return TRUE;
        return FALSE;
    }

}

// TODO:
// RadioSelect


// cheesy helper class to work around php lack of closures/lambdas
class TestInArray
{
    function __construct($haystack) { $this->haystack=$haystack; }
    function test($needle) { return in_array($needle,$this->haystack); }
}


class CheckboxSelectMultiple extends SelectMultiple {
    function render($name, $value, $attrs=null, $choices=array()) {
        if(is_null($value))
            $value=array();
        $has_id = ($attrs && array_key_exists('id',$attrs)) ? TRUE:FALSE;
        $attrs['name']=$name . '[]';
        $final_attrs = $this->build_attrs($attrs);

        $output=array('<ul>');

        // TODO: extra choices not yet supported because of annoying php
        // array issues with numeric indexes. grrr.
        assert(!$choices);

        $i=0;
        foreach($this->choices as $option_value=>$option_label) {
            // If an ID attribute was given, add a numeric index as a suffix,
            // so that the checkboxes don't all have the same ID attribute.
            if($has_id) {
                $final_attrs['id'] = sprintf('%s_%s', $attrs['id'], $i);
                $label_for = sprintf(' for="%s"', $final_attrs['id']);
            } else {
                $label_for = '';
            }
            ++$i;

            $check_test = array(new TestInArray($value),"test");
            $cb = new CheckboxInput($final_attrs,$check_test);
            $output[] = sprintf("<li><label%s>%s %s</label></li>",
                $label_for,
                $cb->render($attrs['name'],$option_value),
                $option_label );
        }
        $output[] = '</ul>';
        return join("\n",$output );
    }
}



// TODO:
// MultiWidget
// SplitDateTimeWidget
// SplitHiddenDateTimeWidget

?>
