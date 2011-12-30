Model Generator, pre-alpha version
==================================

    Usage: php index.php --uri='generator/index' --name="" --fields="" [--directory] [--save] [--force]
    
      --name           Model name
      --fields         Fields (syntax below)
      --directory      Model directory (defaults to APPPATH.'classes/model/'
      --save           Save to file (by default, generator throws everything up to STDOUT)
      --force          Force save (ignores if file already exists)

Information
===========

This model generator should be used *only* in conjunction with Kohana3-CRUD.
It's designed to work with it, uses Formo and basically without it is pretty useless.

If you have any cool ideas about how to improve it, send me a pull request. Code cleanup would also be nice.
Oh, and grab me a beer while you're at it ;)

Syntax
======

    Example: field_name:field_type[options]; field_name2:field_type2[options2]; (etc)
    
      field_name: field name from database (e.g. id, name, etc)
      field_type: int, varchar, text, blob, (date, datetime, timestamp), primary, file
                  primary - won't be visible
                  file - allows simple upload of images (can be modified later on)
                  (both special types should be used without conjunction with other types, e.g. id:primary doesn't need id:int)
      options:    varchar length or foreign key in relations (see below)

Relations
=========

It is possible to create relations directly through generator. Syntax is based on the one above:

    relation_type:related_model[foreign_key]

      relation_type: belongs_to, has_one or has_many
      related_model: model related to the created one (e.g. while creating comments model, belongs_to:entries) 
      foreign_key:   optional foreign key if you don't follow Kohana's convention (model_id)

** YOU SHOULD NOT ADD FIELD IF YOU HAVE ALREADY CREATED A RELATION FROM IT **
     (it will probably break and it's not really a smart idea, seriously)

@author Michał Matyas <michal@6irc.net>
@license MIT License
@link http://github.com/d4rky-pl/kohana3-generator

