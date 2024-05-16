
<?php
$showQuiz = false;
$quizEnded = false;

// Database configuration
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "users";

$conn = new mysqli($servername, $username, $password, $dbname);

// Check the connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Retrieve user input
    $fullName = $_POST["Full-name"];
    $email = $_POST["username"];

    // Insert user data into the database
    $sql = "INSERT INTO users (user_name, email_id) VALUES ('$fullName', '$email')";
    if ($conn->query($sql) === TRUE) {
        $showQuiz = true;
    } else {
        echo "Error: " . $sql . "<br>" . $conn->error;
    }
}
if (isset($_POST['quizEnded']) && $_POST['quizEnded'] === 'true') {
    $quizEnded = true;
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Online MCQ Test Platform</title>
    <link rel="stylesheet" href="style.css">
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            let quizData;
            let fullName = "<?php echo $fullName; ?>";
            let userScore = 0;
            let answeredQuestions = new Set();
            let quizEnded = false;

            function setFullName(name) {
                fullName = name;
                const usernameSpan = document.getElementById('Username');
                if (usernameSpan) {
                    usernameSpan.textContent = fullName;
                }
            }

            setFullName("<?php echo $fullName; ?>");
            fetch('question-data.php')
                .then(response => response.json())
                .then(data => {
                    // Shuffle the questions here
                    quizData = shuffleArray(data);
                    quizData = quizData.slice(0, 100);

                    const userAnswers = new Array(quizData.length);
                    const questionContainer = document.getElementById('questionContainer');
                    const optionButtons = document.querySelectorAll('.opt-btn');
                    const timerElement = document.getElementById('timer');
                    const usernameSpan = document.getElementById('Username');
                    const userScoreSpan = document.getElementById('UserScore');
                    const questionNavButtons = document.querySelectorAll('.question-nav');
                    const submitTestButton = document.querySelector('.submitTestBtn');

                    let currentQuestionIndex = 0;
                    let userScore = 0;
                    let timeLimitInSeconds = 5400;
                    let timer;
                    let questionAttempted = false;
                    let selectedOptionIndex = null;
                    let visitedQuestions = new Set();

                    displayQuestion();
                    startTimer();

                    window.selectOption = function (index) {
                        if (!questionAttempted && !quizEnded) {
                            selectedOptionIndex = index;
                            checkAnswer(selectedOptionIndex);
                            disableOptionButtons();
                            highlightSelectedOption(selectedOptionIndex);
                            optionButtons[index].classList.add('clicked');
                            questionNavButtons[currentQuestionIndex].classList.add('clicked');
                            markAsVisited(currentQuestionIndex + 1);
                            answeredQuestions.add(currentQuestionIndex + 1);
                        }
                    };

                    questionNavButtons.forEach(function (button, index) {
                        button.addEventListener('click', function () {
                            const questionNumber = index + 1;
                            if (answeredQuestions.has(questionNumber) || visitedQuestions.has(questionNumber)) {
                                // If the question has been answered or visited, disable changing the question
                                return;
                            }
                            jumpToQuestion(questionNumber);
                            resetOptionColors();
                            enableOptionButtons();
                            button.classList.add('clicked');
                        });
                    });

                    submitTestButton.addEventListener('click', function () {
                        showFinalScore();
                    });

                    function showFinalScore() {
                        clearInterval(timer);

                        console.log("Final userScore:", userScore);

                        const xhr = new XMLHttpRequest();
                        xhr.open("POST", "update_score.php", true);
                        xhr.setRequestHeader("Content-type", "application/x-www-form-urlencoded");

                        const email = encodeURIComponent('<?php echo str_replace(["\r", "\n"], '', $email); ?>');
                        const params = `userScore=${userScore}&email=${email}`;

                        xhr.onreadystatechange = function () {
                            if (xhr.readyState == 4 && xhr.status == 200) {
                                console.log(xhr.responseText);
                            }
                        };

                        xhr.send(params);

                        // Display final score and ask if user wants to submit the test
                        const confirmSubmit = (`End of Quiz. Your final score is: ${userScore}. Do you want to submit the test?`);

                        if (confirmSubmit) {
                            quizEnded = true;
                            disableOptionButtons();
                            disableSubmitButton();
                            displayPerformanceStats();
                        }
                    }

                    function displayPerformanceStats() {
                        const attemptedQuestionsSpan = document.getElementById('attempted-questions');
                        const correctAnswersSpan = document.getElementById('correct-answers');
                        const wrongAnswersSpan = document.getElementById('wrong-answers');
                        const userNameSpan = document.getElementById('user-name');
                        const scorePercentageSpan = document.getElementById('score-percentage');

                        const totalAttempted = getTotalAttemptedQuestions();
                        const correctAnswers = userScore;
                        const wrongAnswers = totalAttempted - userScore;
                        const scorePercentage = ((userScore / quizData.length) * 100).toFixed(2);

                        attemptedQuestionsSpan.textContent = totalAttempted;
                        correctAnswersSpan.textContent = correctAnswers;
                        wrongAnswersSpan.textContent = wrongAnswers;
                        userNameSpan.textContent = fullName;
                        scorePercentageSpan.textContent = `${scorePercentage}%`;

                        // Open a new window to display performance stats
                        const newWindow = window.open('', '_blank');
                        newWindow.document.write(`<h1>Quiz Performance</h1>`);
                        newWindow.document.write(`<p>Questions Attempted: ${totalAttempted}</p>`);
                        newWindow.document.write(`<p>Correct Answers: ${correctAnswers}</p>`);
                        newWindow.document.write(`<p>Wrong Answers: ${wrongAnswers}</p>`);
                        newWindow.document.write(`<p>User Name: ${fullName}</p>`);
                        newWindow.document.write(`<p>Score Percentage: ${scorePercentage}%</p>`);
                    }

                    function disableSubmitButton() {
                        submitTestButton.disabled = true;
                    
                    }

                    function changeButtonColors(isCorrect) {
                        const currentQuestionNavButton = document.getElementById(`navBtn_${currentQuestionIndex + 1}`);
                        const currentOptButton = document.getElementById(`option${selectedOptionIndex + 1}`);

                        currentOptButton.classList.remove('correct-answer', 'wrong-answer');
                        currentQuestionNavButton.classList.remove('correct-answer', 'wrong-answer');

                        if (isCorrect) {
                            currentOptButton.classList.add('correct-answer');
                            currentQuestionNavButton.classList.add('correct-answer');
                        } else {
                            currentOptButton.classList.add('wrong-answer');
                            currentQuestionNavButton.classList.add('wrong-answer');

                            const correctOptionIndex = quizData[currentQuestionIndex].correctOption;
                            const correctOptButton = document.getElementById(`option${correctOptionIndex + 1}`);
                            correctOptButton.classList.add('correct-answer');

                            currentQuestionNavButton.classList.add('wrong-answer');
                        }
                    }

                    function optionClickHandler(event) {
                        const optionIndex = Array.from(optionButtons).indexOf(event.target);
                        if (selectedOptionIndex === null) {
                            selectOption(optionIndex);
                        }
                    }


                    // Add the following function after the existing functions in the script
                    function getTotalAttemptedQuestions() {
                        let totalAttempted = 0;
                        for (let i = 0; i < userAnswers.length; i++) {
                            if (userAnswers[i] !== undefined) {
                                totalAttempted++;
                            }
                        }
                        return totalAttempted;
                    }

     
                    function shareOnFacebook() {
                        const attemptedQuestions = getTotalAttemptedQuestions();
                        const correctAnswers = userScore;
                        const quizUrl = window.location.href;
                        const shareUrl = `https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(quizUrl)}`;
                        window.open(shareUrl, '_blank');
                    }

                    function shareOnTwitter() {
                        const attemptedQuestions = getTotalAttemptedQuestions();
                        const correctAnswers = userScore;
                        const quizUrl = window.location.href;
                        const shareText = `Check out my quiz performance! I attempted ${attemptedQuestions} questions and got ${correctAnswers} correct answers!`;
                        const shareUrl = `https://twitter.com/intent/tweet?text=${encodeURIComponent(shareText)}&url=${encodeURIComponent(quizUrl)}`;
                        window.open(shareUrl, '_blank');
                    }

                    function disableSubmitButton() {
                        submitTestButton.disabled = true;
                    }

                    function displayQuestion() {
    const currentQuestion = quizData[currentQuestionIndex];
    questionContainer.textContent = `(Q${currentQuestionIndex + 1} Of ${quizData.length}) ${currentQuestion.question}`;

    // Assign options to buttons without shuffling
    for (let i = 0; i < currentQuestion.options.length; i++) {
        optionButtons[i].textContent = currentQuestion.options[i];
        optionButtons[i].style.backgroundColor = '';
        optionButtons[i].disabled = visitedQuestions.has(currentQuestionIndex + 1) || questionAttempted;
    }

    usernameSpan.textContent = fullName;

    if (questionAttempted && selectedOptionIndex !== null) {
        highlightSelectedOption(selectedOptionIndex);
        disableOptionButtons();
    }

    questionNavButtons.forEach(function (button, index) {
        const questionNumber = index + 1;
        if (visitedQuestions.has(questionNumber)) {
            if (questionNumber === currentQuestionIndex + 1) {
                button.classList.add('visited');
            }
        }
    });

    questionAttempted = false;
    selectedOptionIndex = null;
}


                    function markAsVisited(questionNumber) {
                        const index = questionNumber - 1;
                        questionNavButtons[index].classList.add('visited');
                        visitedQuestions.add(questionNumber);
                    }

                    function checkAnswer(selectedOptionIndex) {
                        if (questionAttempted || currentQuestionIndex >= quizData.length) {
                            return;
                        }

                        const currentQuestion = quizData[currentQuestionIndex];
                        const correctOptionIndex = currentQuestion.correctOption;

                        if (selectedOptionIndex === correctOptionIndex) {
                            userScore++;
                            changeButtonColors(selectedOptionIndex === correctOptionIndex);
                            changeButtonColors(true);
                        } else {
                            changeButtonColors(false);
                        }

                        questionAttempted = true;
                        updateScoreDisplay();
                        userAnswers[currentQuestionIndex] = selectedOptionIndex;

                        if (selectedOptionIndex === correctOptionIndex) {
                            optionButtons[selectedOptionIndex].classList.add('correct-answer');
                        } else {
                            optionButtons[selectedOptionIndex].classList.add('wrong-answer');
                        }

                        questionNavButtons[currentQuestionIndex].classList.add(selectedOptionIndex === correctOptionIndex ? 'correct-answer' : 'wrong-answer');
                        disableOptionButtons();
                    }

                    function highlightSelectedOption(selectedOptionIndex) {
                        const optionButtons = document.querySelectorAll('.opt-btn');

                        optionButtons.forEach((button, index) => {
                            if (index === selectedOptionIndex) {
                                button.classList.add('selected');
                            } else {
                                button.classList.remove('selected');
                            }
                        });
                    }

                    function resetOptionColors() {
                        const optionButtons = document.querySelectorAll('.opt-btn');

                        optionButtons.forEach(button => {
                            button.classList.remove('correct-answer', 'wrong-answer', 'not-answered', 'clicked');
                        });
                    }

                    function selectOption(selectedOptionIndex) {
                        resetOptionColors();
                        highlightSelectedOption(selectedOptionIndex);
                    }

                    function disableOptionButtons() {
                        optionButtons.forEach(button => {
                            button.removeEventListener('click', optionClickHandler);
                            button.disabled = true;
                        });
                    }

                    function enableOptionButtons() {
                        optionButtons.forEach(button => {
                            button.addEventListener('click', optionClickHandler);
                            button.disabled = false;
                        });
                    }

                    function startTimer() {
                        timer = setInterval(function () {
                            if (timeLimitInSeconds > 0) {
                                timeLimitInSeconds--;
                                updateTimerDisplay();
                            } else {
                                clearInterval(timer);
                                alert('Time is up! Test will be automatically submitted.');
                                showFinalScore();
                            }
                        }, 1000);
                    }

                    function resetTimer() {
                        timeLimitInSeconds = 5400;
                        updateTimerDisplay();
                    }

                    function updateTimerDisplay() {
                        const minutes = Math.floor(timeLimitInSeconds / 60);
                        const seconds = timeLimitInSeconds % 60;
                        const formattedTime = `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
                        timerElement.textContent = `Timer: ${formattedTime}`;
                    }

                    function jumpToQuestion(questionNumber) {
                        if (questionNumber >= 1 && questionNumber <= quizData.length) {
                            currentQuestionIndex = questionNumber - 1;
                            displayQuestion();
                        }
                    }

                    function updateScoreDisplay() {
                        userScoreSpan.textContent = userScore;
                    }

                    // Function to shuffle array
                    function shuffleArray(array) {
                        for (let i = array.length - 1; i > 0; i--) {
                            const j = Math.floor(Math.random() * (i + 1));
                            [array[i], array[j]] = [array[j], array[i]];
                        }
                        return array;
                    }

                    // Function to share on Facebook
                    function shareOnFacebook() {
                        const quizUrl = window.location.href;
                        const shareUrl = `https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(quizUrl)}`;
                        window.open(shareUrl, '_blank');
                    }

                    // Function to share on Twitter
                    function shareOnTwitter() {
                        const quizUrl = window.location.href;
                        const shareText = `Check out my quiz performance!`;
                        const shareUrl = `https://twitter.com/intent/tweet?text=${encodeURIComponent(shareText)}&url=${encodeURIComponent(quizUrl)}`;
                        window.open(shareUrl, '_blank');
                    }

                    // Event listener for option buttons
                    function optionClickHandler(event) {
                        const optionIndex = Array.from(optionButtons).indexOf(event.target);
                        if (selectedOptionIndex === null) {
                            selectOption(optionIndex);
                        }
                    }

                    optionButtons.forEach(button => {
                        button.addEventListener('click', optionClickHandler);
                    });

                })
                .catch(error => console.error('Error fetching questions:', error));
        });
    </script>
</head>

<body>

    <?php if (!$showQuiz) { ?>
        <div class="login-container">
            <h2>Welcome to MCQ test!</h2>
            <p>Instructions:</p>
            <ul>
                <li>Read each question carefully before answering.</li>
                <li>You will have random questions form anywhere.</li>
                <li>If you answered once then you will not able to visit to that question again</li>
                <li>You will have 90 minutes to pass the test</li>
                <li>Fill the details mentioned below and click start</li>
                <li>Good luck and have fun!</li>
            </ul>
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                <label for="Full-name">Full name:</label>
                <input type="text" name="Full-name" required>
                <label for="username">Email:</label>
                <input type="email" id="username" name="username" required>
                <input type="submit" value="Start" title="Click here to play">
            </form>
        </div>
    <?php } else { ?>
        <!-- Quiz Page Content -->
        <div id="performance" >
        <p><strong>Username:</strong>
                <span id="Username"><?php echo $fullName; ?></span>
            </p>
            <p><strong>Correct answers:</strong>
                <span id="UserScore">0</span>
            </p>
        </div>
        <div id="user-navigation">
           
            <h5>Use these buttons to jump to any question</h5>
            <ul>
                <li>Orage indicates not attempted</li>
                <li>Green indicates right</li>
                <li>Red indicates wrong</li>
            </ul>
            <div class="button-container">
                <?php
                for ($i = 1; $i <= 100; $i++) {
                    $class = ($i == 1) ? 'clicked' : '';
                    echo "<button class='question-nav $class' onclick='jumpToQuestion($i)' id='navBtn_$i'>Q$i</button>";
                }
                ?>
            </div>
            <div>
                <button class="submitTestBtn">Submit Test</button>
            </div>
        </div>
        <div id="timer"> Timer : 00:00</div>

        <div class="mainbox">
            <div id="questionContainer" class="box questionbx">Questions</div>
            <div class="buttons">
                <button id="option1" class="opt-btn" type="button" onclick="selectOption(0)">Option 1</button>
                <button id="option2" class="opt-btn" type="button" onclick="selectOption(1)">Option 2</button>
                <button id="option3" class="opt-btn" type="button" onclick="selectOption(2)">Option 3</button>
                <button id="option4" class="opt-btn" type="button" onclick="selectOption(3)">Option 4</button>
            </div>
        </div>

        <!-- Performance Stats -->
        <div id="performance-stats" style="display: none;">
            <h3>Quiz Performance</h3>
            <p>Questions Attempted: <span id="attempted-questions"></span></p>
            <p>Correct Answers: <span id="correct-answers"></span></p>
            <p>Wrong Answers: <span id="wrong-answers"></span></p>
            <p>User Name: <span id="user-name"><?php echo $fullName; ?></span></p>
            <p>Score Percentage: <span id="score-percentage"></span></p>
            <div id="social-sharing">
                <button onclick="shareOnFacebook()">Share on Facebook</button>
                <button onclick="shareOnTwitter()">Share on Twitter</button>
            </div>
            <style>
                #performance-stats {
    display: none;
    width: 300px;
    margin: 20px auto;
    padding: 20px;
    border: 1px solid #ccc;
    border-radius: 5px;
    background-color: #f9f9f9;
}

#performance-stats h3 {
    font-size: 20px;
    font-weight: bold;
    color: #333;
    margin-bottom: 10px;
}

#performance-stats p {
    margin-bottom: 5px;
}

#performance-stats span {
    font-weight: bold;
    color: #666;
}

#social-sharing {
    margin-top: 10px;
}

#social-sharing button {
    padding: 5px 10px;
    margin-right: 10px;
    border: none;
    border-radius: 3px;
    background-color: #3b5998; /* Facebook blue */
    color: #fff;
    cursor: pointer;
}

#social-sharing button:last-child {
    margin-right: 0;
    background-color: #1da1f2; /* Twitter blue */
}

            </style>
        </div>
    <?php } ?>
</body>

</html>
