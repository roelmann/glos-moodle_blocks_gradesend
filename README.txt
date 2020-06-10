/******************************************************************************
 * Grade Send block for University of Gloucestershire                         *
 * Developed by Richard Oelmann in support of ADS/LTR team (Steve Howes)      *
 *                                                                            *
 * Requirement - to replace the previous automatic sending of grades with an  *
 * on-demand button to click, ensuring marks, grades and relevant assessment  *
 * flags can be sent as required, including over-writing pre-existing grades  *
 *                                                                            *
 ******************************************************************************/

Note: This block deals with pushing the marks, grades and flags to an
integrations database table where the UoG data warehouse picks them up for
consumption. It does not deal with pushing those grades direct to Student
Records. Neither does this block impact the release of grades to students, which
still happens automatically on the 20 working days.

The php code for the block is fully commented.

The block should be installed into UoGMoodle/blocks in the normal manner -
either through git, or through the Moodle UI, or through copying the block code
directly into the relevant location, and running Site Admin install processes.

It is recommended that the block is applied by an admin on the front page,
Site Home, and set to appear throughout the site, in a top block region, as
currently available in the UoG themes - this is preferable to leaving it in the
default right block region as that would further encroach on screen width on an
already wide table when looking at Assignment grades and feedback. The block
is designed to show up on an Assignment Grading page (view all submissions) and
on any quiz page. It will also show on the site home page for admins only.

Currently the block does not implement any lock on resending grades after an
exam board as there is no way for this to be determined. If, in future, Agreed
Grades are being sent back to Moodle via the Integrations tables, then such a
lock could be implemented, based on whether a value exists in that field.
However, there is currently no integration which makes use of such data, and so
no guarantee that the data exists to use as a key for such a lock.
In this current case, it is envisaged that DataWarehouse (which does hold that
data), or SITS itself, would be the vehicle for that overwrite lock.


Usage:
An instruction/description block of text is available, which can be edited
through the normal UI language customisation process.

Tutors should tick the option to confirm that grades are indeed ready to send,
then click the Send Grades button.
On an assignment, the user will be reverted to the main assignment page [Dev
note: this can be adjusted by changing the header redirect, but code would also
need to be introduced to change the URL for quizes - the current code works for
both.]

SUSPECT BREACH FLAG - SB: Tutors should click the Send Grades button to send an
SB flag immediately, regardless of whether other grades are ready to send. Other
grades can be sent later, when finalised.

Tutors can check that grades have been sent using the 'View Integrations' button.
[Dev note: the View Integrations page provides a very simple table of the Grades
currently in the Integrations] database. It would be a straightforward task to
implement bootstrap styling to render this in a more attractive form, if
required. Likewise, the block itself is largely unstyled, and will pick up
general theme default styling.]
[Dev note2: The View Integrations page does not provide a full view of the data
held on the usr_data_student_assessments table, it provides sufficient for staff
to determine that grades have been correctly sent as an overview. More detailed
investigation would need to be carried out at the database itself.]
