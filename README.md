# ACL

*ACL module for Kohana 3.0.x*

- **Module Versions:** 1.0.x
- **Module URL:** <http://github.com/synapsestudios/kohana-acl>
- **Compatible Kohana Version(s):** 3.0.x

## Description

The ACL module for Kohana 3.x builds upon and extends the Auth module to add 
ACL functionality to Kohana. You do not have to change anything about Auth to
use this module, and it does not affect the behavior of Auth in any way.

First of all, the ACL module includes a new type of model called 
"capabilities". They are similar to roles, but are more specific. Roles should be
used to represent what a user *is*, and capabilities are used to represent what
a user is allowed to *do*. Capabilities will most likely correspond to an
action on a controller. For example, you might guard the **delete** action on 
the **article** controller by enforcing the rule that the current user must have
a capability called "delete_article" (or something similar).

ACL rules are defined in such a way that they have a specific scope, and this 
scope can cascade onto less specific rules. This cascading system allows multiple
rules that could apply to any one request. Each request has 3 parts: a directory,
a controller, and an action. These 3 parts represent 3 levels of the cascading 
rule system where a rule protecting the action is the most specific. ACL, by
default, blocks access to all requests, therefore, the ACL rules are used to 
whitelist users. You can allow users specifically by ID (or username), by the 
capabilities they have, or by the roles they have.

## Requirements & Installation

The ACL module requires that the Auth Module and ORM Module already be installed
and setup. In order for the ACL module to work properly, the following things 
must be done:

1. The ACL module needs to be enabled in bootstrap, **it should appear in the 
modules list before Auth**.
2. The `acl.sql` file needs to be run to add the ACL module's database tables 
and relationships.
3. If you are overwriting the role, capability, or user models in your 
application, be sure to extend Model_ACL_Role, Model_ACL_Capability and 
Model_ACL_User classes, respectively.
4. You need to write an ACL rules file for your application.
5. The bootstrap file needs to be edited. 
    1. The bootstrap file should `require` the ACL rules file.
    2. The bootstrap needs to run the `ACL::instance()->authorize();` sometime 
	after the rules file is included and before request is executed.
