<?php

return [

    'background_id' => env('TRELLO_BOARD_BACKGROUND_ID', '69f0b6192e6fa9155c7e4e10'),

    'instruction_card_name_suffix' => '(Instructions - Do not delete this card)',

    'example_card_name_prefix' => 'EXAMPLE',

    /*
    |--------------------------------------------------------------------------
    | Template lists (left-to-right order)
    |--------------------------------------------------------------------------
    */
    'lists' => [
        'requests' => 'REQUESTS (QUEUE) COLUMN',
        'in_progress' => 'IN PROGRESS COLUMN',
        'draft_review' => 'DRAFT REVIEW COLUMN',
        'revisions' => 'REVISIONS COLUMN',
        'delivered' => 'DELIVERED COLUMN',
    ],

    /*
    |--------------------------------------------------------------------------
    | Instruction sentinel cards (protected from delete/archive)
    |--------------------------------------------------------------------------
    */
    'instruction_cards' => [
        'requests_instructions' => [
            'list_key' => 'requests',
            'name' => 'CARD - Add all new requests here! (Instructions - Do not delete this card)',
            'desc' => <<<'DESC'
Start by adding a new card to this column :wink:

**DISCLAIMER:** This is just an example, you can add the fields you need depending on the type of request you are making.

**It's important that you follow every step mentioned as follow:**

### _**Step 1:**_

_**Please include**_ :sparkles: (See card example)_**:**_

- Title of content (Card title)
- Description of content
- Type of content (blog, LinkedIn, email marketing, e-book, etc.)
- Goal and Objective
- Target audience (Startup Founders, SaaS operators, entrepreneurs, etc.)
- Tone / Style (Professional, conversational, insightful, casual, storytelling, etc.)
- Length (number of words)
- CTA or recommendations, if applicable
- Any references, examples or supporting documents
- Any additional writing requirements

### _**Step 2:**_ Once you finish adding your request add the label “Request Completed” by using the following button at the top of the card:

![image.webp](https://trello.com/1/cards/69ecc8ec28fb565609b10c55/attachments/6a0c4551c092d2a97eabb1a1/previews/6a0c4551c092d2a97eabb1b0/download/image.webp)

### Step 3: Add the current date of request submission by using the following button at the top of the card:

![image.webp](https://trello.com/1/cards/69ecc8ec28fb565609b10c55/attachments/6a0c4620be0d1a5308944827/previews/6a0c4621be0d1a5308944848/download/image.webp)

### Step 4: Then, assign the request to my name by using the following button at the top of the card:

![image.webp](https://trello.com/1/cards/69ecc8ec28fb565609b10c55/attachments/6a0c45be407b2d98b4a4f132/previews/6a0c45bf407b2d98b4a4f149/download/image.webp)

### _**NOTE:**_ If you submit multiple requests, please move the highest priority ones to the top of this column so they can be processed one by one in the order of your choice and add the label “HIGH PRIORITY”.
DESC,
            'label_names' => [],
        ],
        'in_progress_instructions' => [
            'list_key' => 'in_progress',
            'name' => 'CARD - What MayWrites is currently working on! (Instructions - Do not delete this card)',
            'desc' => <<<'DESC'
Hi again! :star:

Once you finish submitting a request, I will move that request card to this column and add the label called “IN PROGRESS”.

### _**NOTE:**_ I will work on one request at a time. If you have multiple requests, I will work on them in the order in which they were placed under the "REQUESTS (QUEUE)" column and as soon as I finish the request I am currently working on.
DESC,
            'label_names' => ['IN PROGRESS'],
        ],
        'draft_review_instructions' => [
            'list_key' => 'draft_review',
            'name' => 'CARD - See the "almost finished" product! (Instructions - Do not delete this card)',
            'desc' => <<<'DESC'
Yay, we're almost there! :fire:

Once I finish drafting your request, I will be uploading the document to this column in .docx format and add the lable called “DRAFT FOR REVIEW”.

Next Steps:

1. Download draft (Attachment)
2. Review the work done
3. Leave comments

### _**NOTE 1:**_ If you have any comments, you can use the right side of the card to leave your comments (BE DETAILED) or upload the draft with comments using the "Review" tab of the word document.

### _**NOTE 2:**_ If you placed any comments, please add the label "COMMENTS ADDED" to the card. Upon receiving your comments, we will move the card to the "REVISIONS COLUMN" as soon as we finish incorporating the feedback received.

### _**NOTE 3:**_ If you have no comments, please place the label "NO COMMENTS" to the card, we will proceed to move the project to the “DELIVERED COLUMN”, add the “DELIVERED” label and mark the card “Complete”.
DESC,
            'label_names' => ['DRAFT FOR REVIEW', 'COMMENTS ADDED', 'NO COMMENTS'],
        ],
        'revisions_instructions' => [
            'list_key' => 'revisions',
            'name' => 'CARD - The request review is complete! (Instructions - Do not delete this card)',
            'desc' => <<<'DESC'
One step away from completing your project! :muscle:

The card title will read [Request Title - Revision Number]

Example: 5 Habits to Improve Sleep Quality - Revision 1

### _**NOTE 1:**_ The revision number will be updated by us (e.g., Revision 1, Revision 2, Revision 3, etc.) by adding one card per revision according to the number of revisions performed.

### _**NOTE 2:**_ The label on the revision cards will be “REVISION”.

Next steps:

1. Download the draft (Attachment) under the card of the most recent revision
2. Review changes made to the project
3. Leave additional comments, if necessary

### _**NOTE 3:**_ If you have any comments, you can use the right side of the card to leave your comments (BE DETAILED) or upload the draft with comments using the "Review" tab of the word document.

### _**NOTE 4:**_ If you placed any comments, please add the label "COMMENTS ADDED" to the card. Upon receiving your additional comments, we will add a new card with a new revision number (Example: 5 Habits to Improve Sleep Quality - Revision 2) as soon as we finish incorporating the feedback received.

### _**NOTE 5:**_ If you have no comments, please place the label "NO COMMENTS" to the card, we will proceed to move the request to the “DELIVERED COLUMN”, add the “DELIVERED” label and mark the card “Complete”.
DESC,
            'label_names' => ['REVISION', 'COMMENTS ADDED', 'NO COMMENTS'],
        ],
        'delivered_instructions' => [
            'list_key' => 'delivered',
            'name' => 'CARD - Your request has been completed! (Instructions - Do not delete this card)',
            'desc' => <<<'DESC'
Amazing, your project is finished! :gift:

In this column, we will list the completed requests, adding the "DELIVERED" label and checking the card "Complete". You can download the work from the attachments on each card.

It was a pleasure working with you. We hope you are satisfied with our service and hope to work with you again! :two_hearts:
DESC,
            'label_names' => ['DELIVERED'],
        ],
    ],

];
