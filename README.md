# ACL

*ACL module from Kohana 3.x*

- **Module Versions:** 0.8
- **Module URL:** <http://github.com/synapsestudios/kohana-acl>
- **Compatible Kohana Version(s):** 3.0

## Description

The ACL module for Kohana 3.x builds upon and extends the Auth module to add 
ACL functionality to Kohana. You do not have to change anything about Auth to
use this module, and it does not affect the behavior of Auth in any way.

First of all, the ACL module includes a new type of object called 
"capabilities". They are similar to roles, but a more specific. Roles should be
used to represent what a user *is*, and capabilities are used to represent what
a user is allowed to *do*. Capabilities will most often match up directly to an
action on a controller. For example, you might guard the **delete** action on 
the **article** controller by enforcing the rule that the current user must have
a capability called "delete-article" (or something similar).

ACL rules are defined in such a way that they have a specific scope, and this 
scope can cascade onto less specific rules. This cascading system allows that 
there will be, at most, 4 rules that could apply to any one request. Each 
request has 3 parts: a directory, a controller, and an action. These 3 parts
represent 3 levels of the cascading rule system where rule protecting the 
action are the most specific. The 4th level is the default rule of ACL, which
has the default functionality of denying all uses. Therefore, the ACL rules are 
all used to whitelist users.

You can allow users specifically by ID (or username), by the capabilities they
have, or by the roles they have.