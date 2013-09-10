<?php
require_once "../lib/forms.php";




class TestForm extends Form {
    function __construct($data=null,$files=null,$opts=null) {
        $choices = array(
            'r'=>'Red',
            'g'=>'Green',
            'b'=>'Blue',
            'w'=>'White',
            '1'=>'One',
            '2'=>'Two',);
        parent::__construct($data,$files,$opts);
        $this->fields['url'] = new URLField(array('help_text'=>'Enter a URL'));
        $this->fields['email'] = new EmailField(array('help_text'=>'Enter an Email address'));
        $this->fields['when'] = new DateField(array('help_text'=>'Enter a date'));
        $this->fields['username'] = new CharField(
            array( 'required'=>TRUE,
                'min_length'=>6,
                'max_length'=>15,
                'help_text'=>'e.g. Fred Bloggs'));
        $this->fields['password'] = new CharField( array('widget'=>'PasswordInput') );
        $this->fields['picker'] = new ChoiceField( array('choices'=>$choices) );
        $this->fields['desc'] = new CharField( array('widget'=>'TextArea') );
        $this->fields['magic'] = new BooleanField( array() );
        $this->fields['multipicker'] = new MultipleChoiceField( array('choices'=>$choices) );
        $this->fields['multipicker2'] = new MultipleChoiceField( array('choices'=>$choices, 'widget'=>'CheckboxSelectMultiple'));
        $this->fields['int_number'] = new IntegerField(
            array( 'required'=>FALSE,
                'min_value'=>1,
                'max_value'=>10,
                'help_text'=>'Pick a number between 1 and 10'));
        $this->fields['float_number'] = new FloatField(
            array( 'required'=>FALSE,
                'min_value'=>0,
                'max_value'=>1,
                'help_text'=>'Pick a number between 0 and 1'));
    }
}


function view()
{
    $form_opts = array(
        'initial'=>array('username'=>'admin','password'=>'password'),
        /* 'prefix'=>'test', */
    );

    if($_SERVER['REQUEST_METHOD'] == 'POST') {
        $f= new TestForm($_POST,array(),$form_opts);
        if($f->is_valid()) {
            // process form here...
            // then, redirect somewhere to prevent doubleposting
//            header("HTTP/1.1 303 See Other");
//            header("Location: {$url}");
//          return;

        }
    } else {
        // provide an unbound form
        $f = new TestForm(null,null,$form_opts);
    }

    template($f);
}



function template( $f ) {

?>
<html>
<head>
<link rel="stylesheet" type="text/css" href="style.css" />
</head>
<body>
<h1>drongo-forms test</h1>

<?php if($f->is_valid()) { ?>

Cleaned data:
<ul>
<?php foreach( $f->cleaned_data as $name=>$value ) { ?>
<li><?=$name ?>: <? var_dump( $value ); ?></li>
<?php } ?>
</ul>

<?php } else { ?>

<p>A random selection of stuff!</p>
<form action="" method="POST">
<table>
<?= $f->as_table(); ?>
</table>
<input type="submit" />
</form>
<br/>

<?php } ?>

</body>
</html>
<?php
}

view();

?>
