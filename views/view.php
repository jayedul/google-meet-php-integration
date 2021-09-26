<!doctype html>
<html>
    <head>
        <link rel="stylesheet" href="static/style.css"/>
    </head>
    <body>
        <div id="google-meet-wrapper">
            <?php
                if(!$meet->isCredentialLoaded()){
                    ?>
                    <form method="post" enctype="multipart/form-data" class="consent-screen">
                        <div class="gm-upload-area">
                            <input type="file" name="credential" accept=".json"/>
                        </div>
                        <button type="submit" class="button button-primary button-large">
                            Load Credentials
                        </button>
                    </form>
                    <?php
                } else if(!$meet->isAppPermitted()){
                    ?>
                    <div class="consent-screen google-consent-screen-redirect">
                        <p>Go and Grant permissions</p>
                        <a class="button button-primary button-large" href="<?php echo $meet->get_consent_screen_url(); ?>">
                            Allow Permissions
                        </a>
                    </div>
                    <?php
                } else {
                    $meetings = $meet->getMeetingList();

                    if(!count($meetings)) {
                        echo '<p>No meeting found</p>';
                    } else {
                        echo '<table><tbody>';
                        foreach($meetings as $meeting) {
                            echo '<tr>
                                <td>' . $meeting['title'] . '</td>
                                <td>' . $meeting['meeting_link'] . '</td>
                                <td>' . $meeting['start'] . '</td>
                                <td><a href="?action=delete&id='.$meeting['id'].'">Delete</a></td>
                                <td><a href="?action=update&id='.$meeting['id'].'">Update with Random Data</a></td>
                            </tr>';
                        }
                        echo '</tbody></table>';
                    }

                    ?>
                    <a href="?action=create">Create Meeting with Random Data</a>
                    <?php
                }
            ?>
        </div>
    </body>
</html>
