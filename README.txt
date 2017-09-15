- - - - - About this module 

Commerce USPS module provides shipping calculations from the USPS 
WebTools API 

- - - - - Dependencies 

This module depends on the Commerce module 
(http://www.drupal.org/project/commerce). 

In addition, the following modules are required: 

* Commerce Physical - http://www.drupal.org/project/commerce_physical - 
this module is used to define the physical properties (weight and 
dimensions) of each product. This information is necessary to determine 
a shipping estimate. 

* Commerce Shipping (7.x-2.x) - 
http://www.drupal.org/project/commerce_shipping - this provides the 
infrastructure for Commerce UPS to fully integrate with the Commerce 
module. 

- - - - - Installation 

1. Install and enable the module and all dependencies (be sure to use 
the latest versions of everything). Add dimensions and weight fields 
(new field types via the Commerce Physical module) to all shippable 
product types. Populate dimensions and weight fields for all products. 

2. Configure the "Shipping service" checkout pane so that it is on the 
"Shipping" page. The "Shipping service" checkout pane MUST be on a later 
page than the "Shipping information" pane. 
(admin/commerce/config/checkout) 

3. Configure the USPS settings 
(admin/commerce/config/shipping/methods/usps/edit). You'll need to 
create USPS WebTools account and obtain a username via 
https://secure.shippingapis.com/registration/. 

- - - - - Limitations 

Eventually, all of these limitations may be addressed. For now, be 
warned. 

1. Single "Ship from" address for all products. 

2. Doesn't ensure product dimensions are less than default package size 
dimensions. In other words, if you have a product that is 1x1x20 
(volume=20) and your default package size is 5x5x5 (volume=125), even 
though the product won't physically fit in the box, these values will be 
used to calculate the shipping estimate. 

3. Doesn't play Tetris. For example, if you have an order with 14 
products with a combined volume of 50 and your default package size has 
a volume of 60, the shipping estimate will be for a single box 
regardless of if due to the packaging shape they don't actually fit in 
the box. 

4. Doesn't limit the weight of packages. If you're trying to ship a box 
full of lead that weighs 600lbs, this module will let you (instead of 
breaking the order into more packages). 

5. Doesn't account for packing material. If you need to account for 
packing material, then you may want to adjust product dimensions 
accordingly. 

- - - - - Methodology 

Calculating estimated shipping costs is a tricky business, and it can 
get really complicated really quickly. Knowing this, we purposely 
designed this module with simplicity in mind. Here's how it works: 

1. Every order must contain at least one package. 

2. The number of packages is determined by calculating the total volume 
of all products in the order, dividing by the volume of the default 
package size, and rounding up. 

3. The weight of each package is determined by dividing the total weight 
of all products in the order by the number of packages. 

If you need custom functionality, you have several options: 

1. Determine if it is something that can be generalized to suit a number 
of users and submit it via the issue queue as a suggestion for inclusion 
in this module. 

2. Hire one of the maintainers to create a custom module that interfaces 
with Commerce UPS to add your custom functionality. 

3. Break open a text editor and start coding your own custom module.
