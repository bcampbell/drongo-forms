<?php
require_once "../lib/forms.php";




class TestForm extends Form {
    function __construct($data,$files,$opts) {
        $choices = array(
            'r'=>'Red',
            'g'=>'Green',
            'b'=>'Blue',
            'w'=>'White',
            '1'=>'One',
            '2'=>'Two',);
        parent::__construct($data,$files,$opts);
        $this->fields['username'] = new CharField(
            array( 'required'=>TRUE,
                'min_length'=>6,
                'max_length'=>15,
                'help_text'=>'e.g. Fred Bloggs'));
        $this->fields['email'] = new CharField( array('max_length'=>100,
            'label'=>"Electronic Mail") );
        $this->fields['password'] = new CharField( array('widget'=>'PasswordInput') );
        $this->fields['picker'] = new ChoiceField( array('choices'=>$choices) );
        $this->fields['desc'] = new CharField( array('choices'=>$choices,'widget'=>'TextArea') );
        $this->fields['magic'] = new BooleanField( array() );
        $this->fields['multipicker'] = new MultipleChoiceField( array('choices'=>$choices) );
        $this->fields['multipicker2'] = new MultipleChoiceField( array('choices'=>$choices, 'widget'=>'CheckboxSelectMultiple'));
    }
}



$f = new TestForm( $_POST, array(),
    array(
        'initial'=>array('username'=>'admin','password'=>'password'),
        /* 'prefix'=>'test', */
    ) );

page($f);


function page( $f ) {

?>
<html>
<head>
<link rel="stylesheet" type="text/css" href="style.css" />
</head>
<body>
<h1>drongo-forms test</h1>
<p>A random selection of stuff!</p>
<form action="" method="POST">
<table>
<?= $f->as_table(); ?>
</table>
<input type="submit" />
</form>
Form is <?php if( $f->is_bound ) { ?>bound<?php }else{ ?>unbound<?php } ?>.<br/>

Form is <?php if($f->is_valid()) { ?>valid<?php } else { ?>not valid<?php } ?><br/>
<br/>
<?php if($f->is_valid()) { $f->full_clean(); ?>
Cleaned data:
<ul>
<?php foreach( $f->cleaned_data as $name=>$value ) { ?>
<li><?=$name ?>: <? var_dump( $value ); ?></li>
<?php } ?>
</ul>
<?php } ?>
</body>
</html>
<?php
}


