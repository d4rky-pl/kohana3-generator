<?php
/**
 * Model Generator, pre-alpha version
 * ==================================
 *
 * Usage: php index.php --uri='generator/index' --name="" --fields="" [--directory] [--save] [--force]
 *
 *   --name           Model name
 *   --fields         Fields (syntax below)
 *   --directory      Model directory (defaults to APPPATH.'classes/model/'
 *   --save           Save to file (by default, generator throws everything up to STDOUT)
 *   --force          Force save (ignores if file already exists)
 *
 * Information
 * ===========
 *
 * This model generator should be used *only* in conjunction with Kohana3-CRUD.
 * It's designed to work with it, uses Formo and basically without it is pretty useless.
 * 
 * If you have any cool ideas about how to improve it, send me a pull request. Code cleanup would also be nice.
 * Oh, and grab me a beer while you're at it ;)
 *
 * Syntax
 * ======
 *
 * Example: field_name:field_type[options]; field_name2:field_type2[options2]; (etc)
 *
 * field_name: field name from database (e.g. id, name, etc)
 * field_type: int, varchar, text, blob, (date, datetime, timestamp), primary, file
 *             primary - won't be visible
 *             file - allows simple upload of images (can be modified later on)
 *             (both special types should be used without conjunction with other types, e.g. id:primary doesn't need id:int)
 * options:    varchar length or foreign key in relations (see below)
 *
 * Relations
 * =========
 *
 * It is possible to create relations directly through generator. Syntax is based on the one above:
 *
 * relation_type:related_model[foreign_key]
 * 
 * relation_type: belongs_to, has_one or has_many
 * related_model: model related to the created one (e.g. while creating comments model, belongs_to:entries) 
 * foreign_key:   optional foreign key if you don't follow Kohana's convention (model_id)
 * 
 * ** YOU SHOULD NOT ADD FIELD IF YOU HAVE ALREADY CREATED A RELATION FROM IT **
 *      (it will probably break and it's not really a smart idea, seriously)
 *
 * @author Michał Matyas <michal@6irc.net>
 * @license MIT License
 * @link http://github.com/d4rky-pl/kohana3-generator
 * 
 */
class Controller_Generator extends Controller
{

	protected $_rules = array(), $_formo = array(), $_relations = array(), $_methods = array();

	public function before()
	{
		if(!Kohana::$is_cli) exit(1);
		ob_end_clean();
		set_time_limit(0);
		return parent::before();
	}

	public function action_index()
	{
		$options = CLI::options('directory','name','fields','save', 'force');
		if(!isset($options['fields']) || !isset($options['name']))
		{
			return $this->usage();
		}
		else
		{
			$this->_file = (array_key_exists('directory', $options) ? $options['directory'] : APPPATH.'classes/model').'/'.$options['name'].'.php';
			$this->_name = ucfirst($options['name']);
		}

		$fields = explode(";", trim($options['fields']," ;"));
		foreach($fields as $field)
		{
			preg_match("/^(?P<field_name>.*?):(?P<field_type>.*?)(?:\[(?P<options>.*?)\])?$/", $field, $matches);
			if(empty($matches['field_name']) || empty($matches['field_type']))
			{
				echo "Malformed field: $field";
				exit(1);
			}
			$this->add_element($matches);
		}

		if(array_key_exists('save', $options))
		{
			if(is_file($this->_file) && !array_key_exists('force', $options))
			{
				echo "File already exists";
				exit(1);
			}

			file_put_contents($this->_file, $this->render());
		}
		else
		{
			echo $this->render();
		}	
	}

	public function usage()
	{
		global $argv;
		echo 'Usage: php index.php --uri="generator/index" --name="" --fields="" [--directory] [--save] [--force]

  --name           Model name
  --fields         Fields (see source code for syntax)
  --directory      Model directory (defaults to APPPATH."classes/model/"
  --save           Save to file (by default, generator throws everything up to STDOUT)
  --force          Force save (ignores if file already exists)

';

		exit(1);
	}

	protected function add_element($field)
	{		
		$_rules = array();
		$_formo = array();
		$_relations = array('belongs_to' => array(), 'has_one' => array(), 'has_many' => array());

		foreach($field as $k=>$v) $field[$k] = trim($v);

		$options = array();
		if(in_array($field['field_name'], array('belongs_to', 'has_one', 'has_many')))
		{
			if(!isset($field['options']))
			{
				$field['options'] = Inflector::singular($field['field_type']).'_id';
			}
			
			$options = array('foreign_key' => $field['options']);

			switch($field['field_name'])
			{
				/* relations */

				case 'belongs_to':
					$_relations['belongs_to'] = array($field['field_type'] => $options);
					$_formo['orm_primary_val'] = 'id';
				break;

				case 'has_one':
					$_relations['has_one'] = array($field['field_type'] => $options);
					$_formo['orm_primary_val'] = 'id';
				break;

				case 'has_many':
					$_relations['has_many'] = array($field['field_type'] => $options);
					$_formo['orm_primary_val'] = 'id';
				break;
			}
			$_formo['label'] = $field['options'];

			$this->_rules[$field['options']] = array_merge(Arr::get($this->_rules, $field['field_name'], array()), $_rules);
			$this->_formo[$field['options']] = array_merge(Arr::get($this->_formo, $field['field_name'], array()), $_formo);
		}
		else
		{
			switch($field['field_type'])
			{
				case 'int':
					$_rules[] = array('digit');
				break;

				case 'varchar':
					$_rules[] = array('max_length', array(':value', $field['options']));
				break;

				case 'text':
					$_formo['driver'] = 'textarea';
				break;

				case 'blob':
					$_formo['render'] = FALSE;
				break;

				case 'date':
				case 'datetime':
				case 'timestamp':
					$_rules[] = array('date');
					$_formo['callbacks'] = array('pass' => array( array('Model_'.$this->_name.'::format_date', array(':field'))));
					$this->_methods['format_date'] = TRUE;
				break;

				/* special types */

				case 'primary':
					$_formo['render'] = FALSE;
					$_formo['editable'] = FALSE;
				break;

				case 'file':
					$_formo['driver'] = 'file';
					$_formo['callbacks'] = array('pass' => array( array('Model_'.$this->_name.'::upload_file', array(':field', ':last_val'))));
					$this->_methods['upload_file'] = TRUE;						
				break;
			}

			if(Arr::get($_formo, 'render') !== FALSE)
			{
				$_formo['label'] = $field['field_name'];
			}

			$this->_rules[$field['field_name']] = array_merge(Arr::get($this->_rules, $field['field_name'], array()), $_rules);
			$this->_formo[$field['field_name']] = array_merge(Arr::get($this->_formo, $field['field_name'], array()), $_formo);
		}
		$this->_relations = array_merge_recursive($this->_relations, $_relations);
	}

	protected function var_export($array)
	{
		$export = var_export($array, TRUE);
		// clean up numeric arrays (we don't need them here)
		$export = preg_replace("/^(\s*)[0-9]+ =>\s*/m", '$1', $export);
		// change double spaces to tabs.
		// MAY BREAK SOMETHING BUT TOO LAZY TO WRITE REGEXP
		$export = str_replace("  ", "\t", $export);

		// add two tabs on every new line for proper lining...
		$export = preg_replace("/^/m", "\t\t", $export);
		// ...except the first one, lol
		$export = preg_replace("/^\t\t/", "", $export);

		// finally, remove newline between => and 'array'. Looks fugly.
		$export = preg_replace("/=>\s*array/m", "=> array", $export);
		return $export;
	}

	protected function render()
	{
		// Yeah, I should've used a view file, but I'd rather stick
		// to one file in this case

		$render = "";

		$render = 
Kohana::FILE_SECURITY.'
class Model_'.$this->_name.' extends ORM {
// This code has been generated automatically
// It\'s good for prototyping but you should modify it for production
'. 
(count($this->_relations['belongs_to']) ? 
'	protected $_belongs_to = '.self::var_export($this->_relations['belongs_to']).";\n": '') . 

(count($this->_relations['has_one']) ? 
'	protected $_has_one    = '.self::var_export($this->_relations['has_one']).";\n" : '') . 

(count($this->_relations['has_many']) ?
'	protected $_has_many   = '.self::var_export($this->_relations['has_many']).";\n" : '') .'
	public function formo()
	{
		return '.self::var_export($this->_formo).';
	}

	public function rules()
	{
		return '.self::var_export($this->_rules).';
	}
'.
(array_key_exists('format_date', $this->_methods) ? 
'	public static function format_date($field)
	{
		return $field->val(date("Y-m-d H:i:s", strtotime($field->val())));
	}
' : '') . '
'.
(array_key_exists('upload_file', $this->_methods) ? 
'	public static function upload_file($field, $previous_file, $directory = null, $filename = null)
	{		
		if(!Upload::not_empty($file))
		{
			$field->val($field->last_val());
			return true;
		}
		
		if(!empty($previous_file))
		{
			@unlink($directory ?: Upload::$default_directory . "/" . $previous_file);
		}

		$file = $field->val();

		$valid = new Validation(array("file" => $file));
		$valid->rules("file", array(
			array("Upload::type", array(":value", array("jpg","gif","png"))),
			array("Upload::size", array(":value", "4M"))
		));

		if(!$valid->check())
		{
			$errors = $valid->errors();
			foreach($errors as $name => $error)
			{
				$field->error($error[0]);
			}
			return false;
		}

		if($filename === NULL)
		{
			$filename = sha1(uniqid().rand(1,100)).".".pathinfo($file["name"], PATHINFO_EXTENSION);
		}

		if(!Upload::save($field->val(), $filename, $directory))
		{
			$field->error("upload");
			return false;
		}
		return $field->val($filename);
	}
' : '') . '
}';
	return $render;
	}

}
