<?php
require_once 'fields.php';
require_once 'widgets.php';

define( 'NON_FIELD_ERRORS', '__all__' );


//
class DrongoDateTime extends DateTime { function __toString() { return $this->format('Y-m-d H:i:s'); } } 
class DrongoDate extends DateTime { function __toString() { return $this->format('Y-m-d'); } }
class DrongoTime extends DateTime { function __toString() { return $this->format('H:i:s'); } }


// TODO:
//  port more fields/widgets
//  port formsets
//  documentation/examples
//  support widget-specific options (eg render_value flag in PasswordInput)
//  add __toString() support to things where it makes sense...
class BaseForm implements ArrayAccess, Countable
{
    public function __construct($data=null, $files=null, $opts ) {
        $default_opts = array(
            'auto_id'=>'id_%s',
            'prefix'=>null,
            'initial'=>array(),
            /* 'error_class'=>ErrorList, */
            'label_suffix'=>':',
            'empty_permitted'=>FALSE, );

        $opts = array_merge($default_opts,$opts);

        $this->is_bound = ($data || $files) ? TRUE:FALSE;
        $this->data = is_null($data) ? array() : $data;
        $this->files = is_null($files) ? array() : $files;

        $this->auto_id = $opts['auto_id'];
        $this->prefix = $opts['prefix'];
        $this->initial = $opts['initial'];
        /*$this->error_class = $opts['error_class'];*/
        $this->label_suffix = $opts['label_suffix'];
        $this->empty_permitted = $opts['empty_permitted'];


        $this->_changed_data = null;

        $this->fields = array();
        $this->_errors = null;
    }

    // support for array access to easily get at fields:

    // It's a little odd, because writing to a field is used to add (or
    // remove) unbound fields, but reading yields BoundFields, which are
    // much more useful in templates.
    // Not sure if this'll cause problems, but it's how the python version
    // works and makes forms easier to use...

    public function count() {
    	return count($this->fields);
    }

    public function offsetExists($offset) {         
        return (isset($this->fields[$offset]));
    }   

    // get a boundfield
    public function offsetGet($offset) {  
        if ($this->offsetExists($offset)) {
            return new BoundField($this,$this->fields[$offset],$offset);
    	}
    	return false;
    }

    // TODO:
    public function offsetSet($offset, $value) {         
        if ($offset) {
            $this->fields[$offset] = $value;
    	}  else {
            $this->fields[] = $value; 
    	}
    }

    public function offsetUnset($offset) {
        unset($this->fields[$offset]);
    }

    /* TODO: need an iterator that produces boundfields
    public function getIterator() {
        return new ArrayIterator($this->fields);
    }
    */


    // syntactic sugar
    public function __get($v) {
        if( $v=='errors' )
            return $this->_get_errors();
        if( $v=='changed_data' )
            return $this->_get_changed_data();
        if( $v=='media' )
            return $this->_get_media();

        // TODO: should trigger an error here?
        return null;
    }

    /* Returns an Error array for the data provided for the form */
    private function _get_errors() {
        if(is_null($this->_errors)) {
            $this->full_clean();
        }
        return $this->_errors;
    }

    /*
        Returns True if the form has no errors. Otherwise, False. If errors are
        being ignored, returns False.
    */
    public function is_valid() {
        // TODO: 
        return( $this->is_bound && !$this->errors );
    }


    /*
    Returns the field name with a prefix appended, if this Form has a
    prefix set.

    Subclasses may wish to override.
    */
    public function add_prefix($field_name) {
        if(!is_null($this->prefix)) {
            return sprintf('%s-%s', $this->prefix, $field_name);
        } else {
            return $field_name;
        }
    }

    /* Add a 'initial' prefix for checking dynamic initial values */
    public function add_initial_prefix($field_name) {
        return sprintf('initial-%s', $this->add_prefix($field_name));
    }


    /* Helper function for outputting HTML. Used by as_table(), as_ul(), as_p(). */
    private function _html_output($normal_row, $error_row, $row_ender, $help_text_html, $errors_on_separate_row) {
        $top_errors = $this->non_field_errors(); //Errors that should be displayed above all fields.

        $output = array();
        $hidden_fields = array();

        foreach( $this->fields as $name=>$field ) {
            $html_class_attr = '';
            $bf = new BoundField($this,$field,$name);
            // escaped error strings
            $bf_errors = $bf->errors;
            if($bf_errors) {
                foreach( $bf_errors as &$e ) {
                    $e=htmlspecialchars($e);
                }
                unset($e);
            } else {
                $bf_errors = array();
            }

            if( $bf->is_hidden ) {
                if( $bf_errors ) {
                    foreach( $bf_errors as $e ) {
                        $top_errors[] = sprintf('(Hidden field %s) %s',$name,$e);
                    }
                }
                $hidden_fields[] = $bf;
            } else {
                // Create a 'class="..."' attribute if the row should have any
                // CSS classes applied.
                $css_classes = $bf->css_classes();
                if($css_classes)
                    $html_class_attr = sprintf(' class="%s"', $css_classes);

                if($errors_on_separate_row and $bf_errors) {
                    foreach( $bf_errors as $e ) {
                        $output[] = sprintf($error_row,$e);
                    }
                }

                if( $bf->label ) {
                    $label = htmlspecialchars($bf->label);
                    // Only add the suffix if the label does not end in
                    // punctuation.
                    if( $this->label_suffix ) {
                        if(strpos(':?.!',substr($label,-1))===FALSE) {
                            $label .= $this->label_suffix;
                        }
                    }
                    $label = $bf->label_tag($label);
                    $label = $label ? $label : '';
                } else {
                    $label = '';
                }

                $help_text='';
                if( $field->help_text ) {
                    $help_text = vsprintf($help_text_html, array($field->help_text));
                }

                // TODO: original django version has custom classes for
                // collecting errors, which we should probably
                // reimplement, rather than just using bare arrays....
                // see forms.util for details
                $errors = '';
                if( $bf_errors ) {
                    $errors = "<ul class=\"errorlist\">\n";
                    foreach( $bf_errors as $err ) {
                        $errors .= "<li>$err</li>\n";
                    }
                    $errors .= "</ul>\n";
                }

                $params = array( $html_class_attr,
                    $label,
                    $bf->html(),
                    $help_text,
                    $errors );
                $output[] = vsprintf( $normal_row, $params );
            }
        }
        if($top_errors) {
            array_unshift( $output, sprintf($error_row, $this->fmt_errorlist($top_errors)) );
        }

        // Insert any hidden fields in the last row.
        if($hidden_fields) {
            $str_hidden = '';
            foreach($hidden_fields as $hf) {
                $str_hidden .= $hf->html();
            }
            if($output) {
                $last_row = array_pop($output);
                // Chop off the trailing row_ender (e.g. '</td></tr>') and
                // insert the hidden fields.
                // string endswith
                if(substr_compare($last_row, $row_ender, -strlen($row_ender), strlen($row_ender)) !== 0) {
                    // This can happen in the as_p() case (and possibly others
                    // that users write): if there are only top errors, we may
                    // not be able to conscript the last row for our purposes,
                    // so insert a new, empty row.
                    $last_row = vsprintf($normal_row,array('','','','',$html_class_attr));
                }
                $last_row = substr($last_row,0,-strlen($row_ender));
                $last_row .= $str_hidden . $row_ender;
                $output[] = $last_row;
            } else {
                // If there aren't any rows in the output, just append the
                // hidden fields.
                $output[] = $str_hidden;
            }
        }
        return join("\n",$output);
    }

    // TODO: original django version has custom classes for
    // collecting errors, which know how to render themselves.
    // should probably reimplement, rather than just using
    // bare arrays....
    // see forms.util for details
    function fmt_errorlist($errs) {
        if(!$errs)
            return '';
        $out = "<ul class=\"errorlist\">\n";
        foreach( $errs as $err ) {
            $out .= "<li>$err</li>\n";
        }
        $out .= "</ul>\n";
        return $out;
    }

    public function as_table() {
        return $this->_html_output(
            '<tr%1$s><th>%2$s</th><td>%5$s%3$s%4$s</td></tr>',    // normal_row
            '<tr><td colspan="2">%s</td></tr>',    //error_row
            '</td></tr>',  //row_ender
            '<br /><span class="helptext">%s</span>',   //help_text_html
            FALSE); //errors_on_separate_row
    }

    public function as_ul() {
        return $this->_html_output(
            '<li%1$s>%5$s%2$s %3$s%4$s</li>',   //normal_row
            '<li>%s</li>',  //error_row
            '</li>',        // row_ender
            ' <span class="helptext">%s</span>',          //help_text_html
            FALSE);
    }

    public function as_p() {
        return $this->_html_output(
            '<p%1$s>%2$s %3$s%4$s</p>',   // normal_row
            '%s',  //error_row
            '</p>',        // row_ender
            ' <span class="helptext">%s</span>',          //help_text_html
            TRUE);
    }

    /*
        Returns an array of errors that aren't associated with a particular
        field -- i.e., from Form.clean(). Returns an empty array if there
        are none.
    */
    public function non_field_errors() {
        $errs = $this->errors;
        if(array_key_exists(NON_FIELD_ERRORS,$errs)) {
            return $errs[NON_FIELD_ERRORS];
        } else {
            return array();
        }
    }

    /*
        Returns the raw_value for a particular field name. This is just a
        convenient wrapper around widget.value_from_data
    */
    public function _raw_value($fieldname) {
        $field = $this->fields[$fieldname];
        $prefix = $this->add_prefix($fieldname);
        return $field->widget->value_from_data( $this->data, $this->files, $prefix);
    }

    /*Cleans all of self.data and populates self._errors and
       self.cleaned_data.
     */
    function full_clean() {
        $this->_errors = array();

        if(!$this->is_bound ) {
            return;
        }

        $this->cleaned_data = array();

        // If the form is permitted to be empty, and none of the form data has
        // changed from the initial data, short circuit any validation.

        // TODO: implement has_changed()
//        if( $this->empty_permitted and !$this->has_changed() )
//            return;

        $this->_clean_fields();
        $this->_clean_form();
        $this->_post_clean();
        if( $this->_errors ) {
            unset( $this->cleaned_data );
        }

    }


    function _clean_fields() {
        foreach($this->fields as $name=>$field) {
            // value_from_data() gets the data from the data arrays.
            // Each widget type knows how to retrieve its own data, because some
            // widgets split data over several HTML fields.
            $value = $field->widget->value_from_data($this->data, $this->files, $this->add_prefix($name));
            try {
                // TODO: special case for file handling
                $value = $field->clean($value);
                $this->cleaned_data[$name] = $value;

                if(method_exists($this, "clean_{$name}")){
                  $this->cleaned_data[$name] = $this->{"clean_{$name}"}($value);
                }
            } catch (ValidationError $e) {
                $this->_errors[$name] = $e->messages;
                if(array_key_exists($name,$this->cleaned_data)) {
                    unset($this->cleaned_data[$name]);
                }
            }
        }
    }

    function _clean_form() {
        try {
            $this->cleaned_data = $this->clean();
        } catch(ValidationError $e) {
            $this->_errors[NON_FIELD_ERRORS] = $e->messages;
        }
    }

    /* An internal hook for performing additional cleaning after form cleaning
        is complete. 
    */
    function _post_clean() {
    }


    /*
        Hook for doing any extra form-wide cleaning after Field.clean() been
        called on every field. Any ValidationError raised by this method will
        not be associated with a particular field; it will have a special-case
        association with the field named '__all__'.
     */
    function clean() {
        return $this->cleaned_data;
    }


    function has_changed() {
        return $this->changed_data ? TRUE:FALSE;
    }

    private function _get_changed_data() {
        if(is_null($this->changed_data)) {
            $this->_changed_data = array();
            /*
            # XXX: For now we're asking the individual widgets whether or not the
            # data has changed. It would probably be more efficient to hash the
            # initial data, store it in a hidden field, and compare a hash of the
            # submitted data, but we'd need a way to easily get the string value
            # for a given field. Right now, that logic is embedded in the render
            # method of each widget.
             */
            foreach($this->fields as $name=>$field) {
                $prefixed_name = $this->add_prefix($name);
                $data_value = $field->widget->value_from_data($this->data, $this->files, $prefixed_name);
                $initial_value=null;
                if(!$field->show_hidden_initial) {
                    if(array_key_exists($name,$this->initial)) {
                        $initial_value = $this->initial[$name];
                    } else {
                        $initial_value = $field->initial;
                    }
                } else {
                    $initial_prefixed_name = $this->add_initial_prefix($name);
                    $hidden_widget = $field->default_hidden_widget();
                    $initial_value = $hidden_widget->value_from_data(
                        $this->data, $this->files, $initial_prefixed_name);
                }

                if($field->widget->_has_changed($initial_value, $data_value)) {
                    $this->_changed_data[] = $name;
                }
            }
            return $this->_changed_data;
        }
    }


    // return media required by the form and all it's widgets
    function _get_media()
    {
        $m = new Media();
        if(isset($this->form_media)) {
            $m->extend( $this->form_media );
        }

        foreach($this->fields as $name=>$field) {
            $m->extend($field->widget->media);
        }
        return $m;
    }

    // TODO:
    // is_multipart()
    //  hidden_fields()
    //  visible_fields()


}

// Converts 'first_name' to 'First name'
function pretty_name($name) {
    if(!$name)
        return '';
    $name = str_replace('_', ' ', $name);
    return ucwords($name);
}






// A Field plus data
class BoundField
{
    public function __construct( $form, $field, $name ) {
        $this->form = $form;
        $this->field = $field;
        $this->name = $name;
        $this->html_name = $form->add_prefix($name);
        $this->html_initial_name = $form->add_initial_prefix($name);
        $this->html_initial_id = $form->add_initial_prefix($this->auto_id);
        $this->label = $this->field->label ? $this->field->label : pretty_name($name);
        $this->help_text = $field->help_text ? $field->help_text : '';
    }

    public function __get($v) {
        if( $v=='errors' )
            return $this->_errors();
        if( $v=='data')
            return $this->_data();
        if( $v=='is_hidden')
            return $this->_is_hidden();
        if( $v=='auto_id')
            return $this->_auto_id();
        // TODO: should trigger an error here?
        return null;
    }

    public function __toString() {
        return $this->html();
    }

    // Renders this field as an HTML widget.
    public function html() {
        if( $this->field->show_hidden_initial )
            return $this->as_widget() + $this->as_hidden(TRUE);
        $out = $this->as_widget();
        return $out;
    }

    private function _errors() {
        if(array_key_exists($this->name,$this->form->errors))
            return $this->form->errors[$this->name];
    }

/*
        Renders the field by rendering the passed widget, adding any HTML
        attributes passed as attrs.  If no widget is specified, then the
        field's default widget will be used.
 */
    public function as_widget($widget=null, $attrs=null, $only_initial=FALSE) {
        if(!$widget)
            $widget = $this->field->widget;
        if( is_null($attrs) )
            $attrs = array();

        $auto_id = $this->auto_id;
        if( $auto_id and
            !array_key_exists('id', $attrs) and
            !array_key_exists('id', $widget->attrs) )
        {

            if(!$only_initial)
                $attrs['id'] = $auto_id;
            else
                $attrs['id'] = $this->html_initial_id;
        }

        $data = null;
        if(!$this->form->is_bound) {
            /* not bound - use initial value if set */
            $data = $this->field->initial;
            if( array_key_exists($this->name,$this->form->initial) ) {
                /* form has an initial value for this field */
                $data = $this->form->initial[$this->name];
            }
            /* TODO: python version allows a callable here:
            if callable(data):
                data = data()
            */
        } else {
            // TODO: special handling for FileField (see python version)
/* 
            if isinstance(self.field, FileField) and self.data is None:
                data = self.form.initial.get(self.name, self.field.initial)
            else:
                data = self.data
 */
            $data = $this->data;
        }

        $name = $this->html_name;
        if( $only_initial)
            $name = $this->html_initial_name;

        return $widget->render($name, $data, $attrs);
    }


/*
    def as_text(self, attrs=None, **kwargs):
        """
        Returns a string of HTML for representing this as an <input type="text">.
        """
        return self.as_widget(TextInput(), attrs, **kwargs)
*/


/*
    def as_textarea(self, attrs=None, **kwargs):
        "Returns a string of HTML for representing this as a <textarea>."
        return self.as_widget(Textarea(), attrs, **kwargs)
*/


    public function as_hidden($attrs = null) {
        return $this->as_widget($this->field->default_hidden_widget(), $attrs);
    }

    // Returns the data for this BoundField, or None if it wasn't given.
    private function _data() {
        return $this->field->widget->value_from_data($this->form->data, $this->form->files, $this->html_name );
    }


    /*
        Wraps the given contents in a <label>, if the field has an ID attribute.
        Does not HTML-escape the contents. If contents aren't given, uses the
        field's HTML-escaped label.

        If attrs are given, they're used as HTML attributes on the <label> tag.
    */
    public function label_tag($contents=null, $attrs=null) {
        $contents = $contents ? $contents : htmlspecialchars($this->label);
        $widget = $this->field->widget;
        if( array_key_exists('id',$widget->attrs) ) {
            $id = $widget->attr['id'];
        } else {
            $id = $this->auto_id;
        }
        if($id) {
            $contents = sprintf('<label for="%s"%s>%s</label>',
                $widget->id_for_label($id), flatatt($attrs), $contents);
        }
        return $contents;
    }



    /* Returns a string of space-separated CSS classes for this field. */
    function css_classes( $extra_classes=null) {
        if($extra_classes) {
            if(!is_array($extra_classes)) {
                $extra_classes = explode(' ',$extra_classes);
            }
        } else {
            $extra_classes = array();
        }

        if($this->errors && isset($this->form->error_css_class))
            $extra_classes[] = $this->form->error_css_class;
        if($this->field->required && isset($this->form->required_css_class))
            $extra_classes[] = $this->form->required_css_class;
        return join(' ',$extra_classes);
    }

    /* Returns True if this BoundField's widget is hidden. */
    private function _is_hidden() {
        return $this->field->widget->is_hidden;
    }

    /*
        Calculates and returns the ID attribute for this BoundField, if the
        associated Form has specified auto_id. Returns an empty string otherwise.
     */
    private function _auto_id() {
        $auto_id = $this->form->auto_id;
        if( $auto_id && strstr('%s',$auto_id) ) {
            return sprintf( $auto_id, $this->html_name );
        } elseif( $auto_id ) {
            return $this->html_name;
        } else {
            return '';
        }
    }
}


/* in original django code, Form adds syntactic sugar to BaseForm.
 * We'll just leave it at this for now.
 */
class Form extends BaseForm
{
}

