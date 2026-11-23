<?php

declare(strict_types=1);

/**
 * The Exam success course (A9.2f, Sprint 9 decision 5): REOS's eight scripts
 * in English and Spanish, written from the Pearson VUE Texas candidate
 * handbook's exam format and the TREC rules, with two practice questions per
 * lesson. Generated from the authoring script; App\Content\ExamSuccess installs it
 * as lessons waiting for review (screen 44).
 *
 * @return list<array{key: string, title: array{en: string, es: string}, sections: list<array{heading: array{en: string, es: string}, lines: list<array{en: string, es: string}>}>, questions: list<array{topic: string, en: array{prompt: string, options: list<string>, explanation: string}, es: array{prompt: string, options: list<string>, explanation: string}, correct: int}>}>
 */
return [
    [
        'key' => 'how-the-exam-is-built',
        'title' => ['en' => 'How the exam is built', 'es' => 'Cómo está armado el examen'],
        'sections' => [
            ['heading' => ['en' => 'Two portions', 'es' => 'Dos partes'], 'lines' => [
                ['en' => 'The Texas sales agent exam has two portions: a national portion about real estate everywhere, and a Texas portion about Texas law and TREC rules.', 'es' => 'El examen de agente de ventas de Texas tiene dos partes: una parte nacional sobre bienes raíces en general y una parte de Texas sobre las leyes de Texas y las reglas de TREC.'],
                ['en' => 'Pearson VUE runs the exam at its test centers, on a computer, one question at a time.', 'es' => 'Pearson VUE administra el examen en sus centros, en computadora, una pregunta a la vez.'],
                ['en' => 'You can take both portions on the same day or on different days. Each one is scored on its own.', 'es' => 'Puede presentar las dos partes el mismo día o en días distintos. Cada una se califica por separado.'],
            ]],
            ['heading' => ['en' => 'The numbers', 'es' => 'Las cifras'], 'lines' => [
                ['en' => 'The national portion has 85 questions and 150 minutes. Eighty are scored, and you pass with 56 right.', 'es' => 'La parte nacional tiene 85 preguntas y 150 minutos. Se califican 80, y aprueba con 56 correctas.'],
                ['en' => 'The Texas portion has 50 questions and 90 minutes. Forty are scored, and you pass with 28 right.', 'es' => 'La parte de Texas tiene 50 preguntas y 90 minutos. Se califican 40, y aprueba con 28 correctas.'],
                ['en' => 'That is 70 percent on each portion. A great score on one portion does not make up for the other.', 'es' => 'Eso es el 70 por ciento en cada parte. Una calificación excelente en una parte no compensa la otra.'],
            ]],
            ['heading' => ['en' => 'Questions that don\'t count', 'es' => 'Preguntas que no cuentan'], 'lines' => [
                ['en' => 'The extra questions in each portion are being tried out for future exams. They look exactly like the others, so you can\'t tell which ones they are.', 'es' => 'Las preguntas adicionales de cada parte se están probando para exámenes futuros. Se ven igual que las demás, así que no puede saber cuáles son.'],
                ['en' => 'Treat every question as if it counts. If one seems strange or unfair, it may be a trial question, so answer it and move on.', 'es' => 'Trate cada pregunta como si contara. Si una le parece rara o injusta, puede ser de prueba: contéstela y siga adelante.'],
            ]],
            ['heading' => ['en' => 'What\'s on it', 'es' => 'Qué temas incluye'], 'lines' => [
                ['en' => 'Each portion follows a content outline with a share of questions per topic. Agency, contracts and finance carry a lot of weight on the national portion.', 'es' => 'Cada parte sigue un temario con una proporción de preguntas por tema. Representación, contratos y financiamiento pesan mucho en la parte nacional.'],
                ['en' => 'Your REOS readiness map uses those same weights, so the topics it asks you to fix are the ones worth the most points.', 'es' => 'Su mapa de preparación en REOS usa esos mismos pesos, así que los temas que le pide reforzar son los que valen más puntos.'],
                ['en' => 'Every question has four options and one best answer. There is no penalty for a wrong answer, so never leave one blank.', 'es' => 'Cada pregunta tiene cuatro opciones y una sola mejor respuesta. Una respuesta incorrecta no resta puntos, así que nunca deje una en blanco.'],
            ]],
            ['heading' => ['en' => 'If a portion doesn\'t go your way', 'es' => 'Si una parte no sale bien'], 'lines' => [
                ['en' => 'You get your score report at the test center. If you pass one portion and not the other, you retake only the one you missed.', 'es' => 'Recibe su informe de calificación en el centro de examen. Si aprueba una parte y no la otra, vuelve a presentar solo la que no aprobó.'],
                ['en' => 'Check the current Pearson VUE candidate handbook for retake rules and how long a passed portion stays valid, because those can change.', 'es' => 'Consulte el manual vigente del candidato de Pearson VUE para conocer las reglas de repetición y cuánto tiempo sigue vigente una parte aprobada, porque pueden cambiar.'],
            ]],
        ],
        'questions' => [
            ['topic' => 'state_rules', 'correct' => 0,
                'en' => ['prompt' => 'How many scored questions must you answer correctly to pass the national portion of the Texas sales agent exam?', 'options' => ['56 of 80', '60 of 85', '28 of 40', '70 of 100'], 'explanation' => 'The national portion has 80 scored questions, and 56 right (70 percent) passes it.'],
                'es' => ['prompt' => '¿Cuántas preguntas calificadas debe contestar correctamente para aprobar la parte nacional del examen de agente de ventas de Texas?', 'options' => ['56 de 80', '60 de 85', '28 de 40', '70 de 100'], 'explanation' => 'La parte nacional tiene 80 preguntas calificadas, y 56 correctas (el 70 por ciento) la aprueban.']],
            ['topic' => 'state_rules', 'correct' => 1,
                'en' => ['prompt' => 'You passed the Texas portion but not the national portion. What do you retake?', 'options' => ['Nothing; the average passes', 'Only the national portion', 'Both portions', 'Only the Texas portion'], 'explanation' => 'Each portion is scored on its own, so you retake only the portion you didn\'t pass.'],
                'es' => ['prompt' => 'Aprobó la parte de Texas pero no la parte nacional. ¿Qué vuelve a presentar?', 'options' => ['Nada; el promedio aprueba', 'Solo la parte nacional', 'Ambas partes', 'Solo la parte de Texas'], 'explanation' => 'Cada parte se califica por separado, así que vuelve a presentar solo la que no aprobó.']],
        ],
    ],
    [
        'key' => 'timing',
        'title' => ['en' => 'Timing and pacing', 'es' => 'Tiempo y ritmo'],
        'sections' => [
            ['heading' => ['en' => 'Your minute budget', 'es' => 'Su presupuesto de minutos'], 'lines' => [
                ['en' => 'On the national portion you have about one minute and 45 seconds per question. On the Texas portion, about one minute and 48 seconds.', 'es' => 'En la parte nacional tiene aproximadamente un minuto y 45 segundos por pregunta. En la parte de Texas, aproximadamente un minuto y 48 segundos.'],
                ['en' => 'Most questions take far less. The time you save on easy ones pays for the hard ones and for your review at the end.', 'es' => 'La mayoría de las preguntas toman mucho menos. El tiempo que ahorra en las fáciles paga las difíciles y su revisión al final.'],
            ]],
            ['heading' => ['en' => 'Two passes', 'es' => 'Dos vueltas'], 'lines' => [
                ['en' => 'On your first pass, answer every question you know. If one needs more than a minute, pick your best answer, flag it and move on.', 'es' => 'En la primera vuelta, conteste todas las preguntas que sepa. Si una necesita más de un minuto, elija su mejor respuesta, márquela y siga.'],
                ['en' => 'Always choose an answer before you flag. If time runs out, a flagged question with an answer can still earn the point.', 'es' => 'Siempre elija una respuesta antes de marcar. Si se acaba el tiempo, una pregunta marcada con respuesta todavía puede sumar el punto.'],
                ['en' => 'On your second pass, go back to the flagged questions with a fresh mind. Many become easier after you have seen the rest of the exam.', 'es' => 'En la segunda vuelta, regrese a las preguntas marcadas con la mente fresca. Muchas se vuelven más fáciles después de ver el resto del examen.'],
            ]],
            ['heading' => ['en' => 'Checkpoints', 'es' => 'Puntos de control'], 'lines' => [
                ['en' => 'Use checkpoints instead of watching the clock on every question.', 'es' => 'Use puntos de control en lugar de mirar el reloj en cada pregunta.'],
                ['en' => 'National portion: by question 40, aim to have used about 70 minutes. Texas portion: by question 25, about 45 minutes.', 'es' => 'Parte nacional: para la pregunta 40, procure haber usado unos 70 minutos. Parte de Texas: para la pregunta 25, unos 45 minutos.'],
                ['en' => 'If you are behind, speed up on the next few questions rather than rushing at the very end.', 'es' => 'Si va atrasado, acelere en las siguientes preguntas en lugar de apurarse al final.'],
            ]],
            ['heading' => ['en' => 'Practise the clock', 'es' => 'Practique con el reloj'], 'lines' => [
                ['en' => 'Take the REOS simulations in Exam mode. The timer runs like the real one and doesn\'t pause.', 'es' => 'Haga los simulacros de REOS en modo examen. El temporizador funciona como el real y no se detiene.'],
                ['en' => 'After each simulation, look at your time per question on the results. If math questions eat your time, the math lab is your next stop.', 'es' => 'Después de cada simulacro, revise su tiempo por pregunta en los resultados. Si las preguntas de matemáticas le consumen el tiempo, su siguiente paso es el laboratorio de matemáticas.'],
                ['en' => 'Leave at least 15 minutes at the end of each portion for your flagged questions.', 'es' => 'Deje al menos 15 minutos al final de cada parte para sus preguntas marcadas.'],
            ]],
        ],
        'questions' => [
            ['topic' => 'state_rules', 'correct' => 2,
                'en' => ['prompt' => 'A question is taking more than a minute on your first pass. What should you do?', 'options' => ['Keep working until you are sure', 'Skip the rest of that topic', 'Pick your best answer, flag it and move on', 'Leave it blank and come back'], 'explanation' => 'An answered, flagged question can still earn the point if time runs out.'],
                'es' => ['prompt' => 'Una pregunta le está tomando más de un minuto en la primera vuelta. ¿Qué debe hacer?', 'options' => ['Seguir trabajando hasta estar seguro', 'Saltarse el resto de ese tema', 'Elegir su mejor respuesta, marcarla y seguir', 'Dejarla en blanco y regresar'], 'explanation' => 'Una pregunta contestada y marcada todavía puede sumar el punto si se acaba el tiempo.']],
            ['topic' => 'state_rules', 'correct' => 3,
                'en' => ['prompt' => 'About how much time do you have per question on the national portion?', 'options' => ['About 30 seconds', 'About 3 minutes', 'About 5 minutes', 'About 1 minute 45 seconds'], 'explanation' => '150 minutes for 85 questions is about one minute and 45 seconds each.'],
                'es' => ['prompt' => '¿Aproximadamente cuánto tiempo tiene por pregunta en la parte nacional?', 'options' => ['Aproximadamente 30 segundos', 'Aproximadamente 3 minutos', 'Aproximadamente 5 minutos', 'Aproximadamente 1 minuto y 45 segundos'], 'explanation' => '150 minutos para 85 preguntas es aproximadamente un minuto y 45 segundos cada una.']],
        ],
    ],
    [
        'key' => 'reading-questions',
        'title' => ['en' => 'Reading NOT, EXCEPT and BEST questions', 'es' => 'Cómo leer preguntas con NO, EXCEPTO y MEJOR'],
        'sections' => [
            ['heading' => ['en' => 'The small words matter', 'es' => 'Las palabras pequeñas importan'], 'lines' => [
                ['en' => 'Many wrong answers come from reading the question too fast, not from not knowing the material.', 'es' => 'Muchas respuestas incorrectas vienen de leer la pregunta demasiado rápido, no de no saber el tema.'],
                ['en' => 'Watch for NOT, EXCEPT, LEAST, FIRST, BEST and MOST LIKELY. They change what the question is asking for.', 'es' => 'Fíjese en NO, EXCEPTO, MENOS, PRIMERO, MEJOR y MÁS PROBABLE. Cambian lo que la pregunta está pidiendo.'],
            ]],
            ['heading' => ['en' => 'NOT and EXCEPT', 'es' => 'NO y EXCEPTO'], 'lines' => [
                ['en' => 'A NOT or EXCEPT question has three true options and one false one. You are looking for the false one.', 'es' => 'Una pregunta con NO o EXCEPTO tiene tres opciones verdaderas y una falsa. Usted busca la falsa.'],
                ['en' => 'Read each option as its own true-or-false statement. Mark true, true, true, and the one left over is your answer.', 'es' => 'Lea cada opción como una afirmación de verdadero o falso. Marque verdadero, verdadero, verdadero, y la que queda es su respuesta.'],
            ]],
            ['heading' => ['en' => 'BEST, FIRST and MOST LIKELY', 'es' => 'MEJOR, PRIMERO y MÁS PROBABLE'], 'lines' => [
                ['en' => 'In a BEST question, more than one option may be partly right. Choose the one that is the most complete and the most correct for the situation.', 'es' => 'En una pregunta de MEJOR, más de una opción puede ser parcialmente correcta. Elija la más completa y la más correcta para la situación.'],
                ['en' => 'In a FIRST question, all the steps may be right, but only one comes first. Think about the order things happen in a real transaction.', 'es' => 'En una pregunta de PRIMERO, todos los pasos pueden ser correctos, pero solo uno va primero. Piense en el orden en que ocurren las cosas en una transacción real.'],
            ]],
            ['heading' => ['en' => 'Long scenarios', 'es' => 'Situaciones largas'], 'lines' => [
                ['en' => 'For a long scenario, read the last sentence first. It tells you what to look for in the story.', 'es' => 'En una situación larga, lea primero la última oración. Le dice qué buscar en la historia.'],
                ['en' => 'Then read the scenario and note only the facts that answer that question. Names, colors and extra details are often there to distract you.', 'es' => 'Después lea la situación y anote solo los datos que responden esa pregunta. Los nombres, colores y detalles adicionales suelen estar para distraerle.'],
                ['en' => 'Answer the question as written, under the rules, not what you think usually happens in practice.', 'es' => 'Responda la pregunta tal como está escrita, según las reglas, no según lo que cree que suele pasar en la práctica.'],
            ]],
            ['heading' => ['en' => 'Absolute words', 'es' => 'Palabras absolutas'], 'lines' => [
                ['en' => 'Options with always, never, all or none are often wrong, because real estate rules have exceptions.', 'es' => 'Las opciones con siempre, nunca, todos o ninguno suelen ser incorrectas, porque las reglas de bienes raíces tienen excepciones.'],
                ['en' => 'That is a clue, not a rule. If the law really is absolute, the absolute option is right.', 'es' => 'Es una pista, no una regla. Si la ley realmente es absoluta, la opción absoluta es la correcta.'],
            ]],
        ],
        'questions' => [
            ['topic' => 'agency', 'correct' => 0,
                'en' => ['prompt' => 'In a question that asks which option is NOT a duty, what are you looking for?', 'options' => ['The one false statement among three true ones', 'The most important duty', 'The duty that comes first', 'Any true statement'], 'explanation' => 'NOT and EXCEPT questions have three true options and one false one; the false one is the answer.'],
                'es' => ['prompt' => 'En una pregunta que pide cuál opción NO es un deber, ¿qué busca?', 'options' => ['La única afirmación falsa entre tres verdaderas', 'El deber más importante', 'El deber que va primero', 'Cualquier afirmación verdadera'], 'explanation' => 'Las preguntas con NO y EXCEPTO tienen tres opciones verdaderas y una falsa; la falsa es la respuesta.']],
            ['topic' => 'contracts', 'correct' => 1,
                'en' => ['prompt' => 'What should you read first in a long scenario question?', 'options' => ['The first sentence only', 'The last sentence, the actual question', 'The names of the people', 'Option A'], 'explanation' => 'The last sentence tells you what to look for in the story, so you can skip details that don\'t matter.'],
                'es' => ['prompt' => '¿Qué debe leer primero en una pregunta con una situación larga?', 'options' => ['Solo la primera oración', 'La última oración, la pregunta en sí', 'Los nombres de las personas', 'La opción A'], 'explanation' => 'La última oración le dice qué buscar en la historia, así puede pasar por alto los detalles que no importan.']],
        ],
    ],
    [
        'key' => 'eliminating',
        'title' => ['en' => 'Eliminating wrong answers', 'es' => 'Cómo descartar respuestas incorrectas'],
        'sections' => [
            ['heading' => ['en' => 'Make it a smaller question', 'es' => 'Haga la pregunta más pequeña'], 'lines' => [
                ['en' => 'Most questions have one or two options you can rule out right away. Each one you remove makes a guess more likely to be right.', 'es' => 'La mayoría de las preguntas tienen una o dos opciones que puede descartar de inmediato. Cada una que elimina hace más probable acertar.'],
                ['en' => 'With four options, a blind guess is right one time in four. With two left, it is right one time in two.', 'es' => 'Con cuatro opciones, adivinar a ciegas acierta una de cada cuatro veces. Con dos, acierta una de cada dos.'],
            ]],
            ['heading' => ['en' => 'What to rule out', 'es' => 'Qué descartar'], 'lines' => [
                ['en' => 'Rule out options that would be illegal or unethical for a license holder, such as hiding a known defect or sharing a client\'s confidential information.', 'es' => 'Descarte las opciones que serían ilegales o poco éticas para un agente con licencia, como ocultar un defecto conocido o revelar información confidencial de un cliente.'],
                ['en' => 'Rule out options that answer a different question, even if they are true statements.', 'es' => 'Descarte las opciones que responden otra pregunta, aunque sean afirmaciones verdaderas.'],
                ['en' => 'Rule out options with terms from the wrong topic, like a lending term in a question about deeds.', 'es' => 'Descarte las opciones con términos de otro tema, como un término de préstamos en una pregunta sobre escrituras.'],
            ]],
            ['heading' => ['en' => 'Clues in the options', 'es' => 'Pistas en las opciones'], 'lines' => [
                ['en' => 'When two options are opposites, one of them is often the answer.', 'es' => 'Cuando dos opciones son opuestas, una de ellas suele ser la respuesta.'],
                ['en' => 'When two options mean the same thing, neither can be the only best answer, so both are usually wrong.', 'es' => 'Cuando dos opciones significan lo mismo, ninguna puede ser la única mejor respuesta, así que normalmente ambas son incorrectas.'],
            ]],
            ['heading' => ['en' => 'Changing answers', 'es' => 'Cambiar respuestas'], 'lines' => [
                ['en' => 'Change an answer only when you have a reason: you misread the question, or a later question reminded you of a rule.', 'es' => 'Cambie una respuesta solo cuando tenga un motivo: leyó mal la pregunta o una pregunta posterior le recordó una regla.'],
                ['en' => 'Don\'t change an answer just because it feels too easy. Many questions are simply easy.', 'es' => 'No cambie una respuesta solo porque le parece demasiado fácil. Muchas preguntas simplemente son fáciles.'],
            ]],
            ['heading' => ['en' => 'Practise it', 'es' => 'Practíquelo'], 'lines' => [
                ['en' => 'In the REOS test screens, use Hint to strike out one wrong option during a drill. Then try striking out options yourself before you answer.', 'es' => 'En las pantallas de prueba de REOS, use Pista para tachar una opción incorrecta durante un ejercicio. Después intente tachar opciones usted mismo antes de responder.'],
                ['en' => 'On answer review, read why each wrong option is wrong, not just why the right one is right.', 'es' => 'En la revisión de respuestas, lea por qué cada opción incorrecta es incorrecta, no solo por qué la correcta es correcta.'],
            ]],
        ],
        'questions' => [
            ['topic' => 'agency', 'correct' => 2,
                'en' => ['prompt' => 'Which option can you usually rule out first?', 'options' => ['Option C', 'One with a number in it', 'One that would be illegal or unethical for a license holder', 'The longest option'], 'explanation' => 'Exam answers follow the law and ethics; an option that breaks them is almost never right.'],
                'es' => ['prompt' => '¿Qué opción puede descartar normalmente primero?', 'options' => ['La opción C', 'Una que tiene un número', 'Una que sería ilegal o poco ética para un titular de licencia', 'La opción más larga'], 'explanation' => 'Las respuestas del examen siguen la ley y la ética; una opción que las viola casi nunca es correcta.']],
            ['topic' => 'agency', 'correct' => 3,
                'en' => ['prompt' => 'When should you change an answer you already chose?', 'options' => ['Whenever it feels too easy', 'Never', 'When you are nervous', 'When you have a reason, such as having misread the question'], 'explanation' => 'Change an answer only for a reason; many questions are simply easy.'],
                'es' => ['prompt' => '¿Cuándo debe cambiar una respuesta que ya eligió?', 'options' => ['Siempre que le parezca demasiado fácil', 'Nunca', 'Cuando está nervioso', 'Cuando tiene un motivo, como haber leído mal la pregunta'], 'explanation' => 'Cambie una respuesta solo por un motivo; muchas preguntas simplemente son fáciles.']],
        ],
    ],
    [
        'key' => 'math-shortcuts',
        'title' => ['en' => 'Math shortcuts', 'es' => 'Atajos de matemáticas'],
        'sections' => [
            ['heading' => ['en' => 'Don\'t fear the math', 'es' => 'No le tema a las matemáticas'], 'lines' => [
                ['en' => 'Math is a small share of the exam, and almost every question uses the same few formulas. You have an on-screen calculator.', 'es' => 'Las matemáticas son una parte pequeña del examen, y casi todas las preguntas usan las mismas pocas fórmulas. Tiene una calculadora en pantalla.'],
                ['en' => 'Before you calculate, estimate. If the answer should be about 9,000 dollars, an option of 900 or 90,000 is out.', 'es' => 'Antes de calcular, estime. Si la respuesta debería ser de unos 9,000 dólares, una opción de 900 o de 90,000 queda descartada.'],
            ]],
            ['heading' => ['en' => 'Part, whole and rate', 'es' => 'Parte, total y tasa'], 'lines' => [
                ['en' => 'Most money questions are one formula: part equals whole times rate.', 'es' => 'La mayoría de las preguntas de dinero son una sola fórmula: la parte es igual al total por la tasa.'],
                ['en' => 'A commission is the sale price times the rate. A 3 percent commission on 350,000 dollars is 10,500 dollars.', 'es' => 'Una comisión es el precio de venta por la tasa. Una comisión del 3 por ciento sobre 350,000 dólares es de 10,500 dólares.'],
                ['en' => 'To find the rate, divide the part by the whole. To find the whole, divide the part by the rate.', 'es' => 'Para encontrar la tasa, divida la parte entre el total. Para encontrar el total, divida la parte entre la tasa.'],
                ['en' => 'The loan-to-value ratio is the loan divided by the value. A 240,000 dollar loan on a 300,000 dollar home is 80 percent.', 'es' => 'La relación préstamo-valor es el préstamo dividido entre el valor. Un préstamo de 240,000 dólares sobre una casa de 300,000 dólares es el 80 por ciento.'],
            ]],
            ['heading' => ['en' => 'Splits', 'es' => 'Repartos'], 'lines' => [
                ['en' => 'For a commission split, work one step at a time: the total commission, then each brokerage\'s share, then the agent\'s share of their brokerage\'s part.', 'es' => 'En un reparto de comisión, avance paso a paso: la comisión total, luego la parte de cada agencia y luego la parte del agente dentro de la de su agencia.'],
            ]],
            ['heading' => ['en' => 'Land and area', 'es' => 'Terrenos y superficie'], 'lines' => [
                ['en' => 'An acre is 43,560 square feet. A section is one square mile, which is 640 acres.', 'es' => 'Un acre tiene 43,560 pies cuadrados. Una sección es una milla cuadrada, que equivale a 640 acres.'],
                ['en' => 'For an area, multiply length by width. For a triangle, multiply them and halve it.', 'es' => 'Para una superficie, multiplique el largo por el ancho. Para un triángulo, multiplíquelos y divida entre dos.'],
            ]],
            ['heading' => ['en' => 'Taxes and prorations', 'es' => 'Impuestos y prorrateos'], 'lines' => [
                ['en' => 'Property tax is the assessed value times the tax rate. A mill is one tenth of a cent, so 25 mills is 0.025.', 'es' => 'El impuesto a la propiedad es el valor tasado por la tasa del impuesto. Un mil es la décima parte de un centavo, así que 25 mils es 0.025.'],
                ['en' => 'For a proration, find the cost per day, then multiply by the days each side owns. Use a 365-day or a 360-day year as the question says.', 'es' => 'Para un prorrateo, calcule el costo por día y multiplíquelo por los días que corresponden a cada parte. Use un año de 365 o de 360 días según indique la pregunta.'],
                ['en' => 'The REOS math lab walks you through each of these step by step, with a new problem every time you finish one.', 'es' => 'El laboratorio de matemáticas de REOS le guía paso a paso en cada uno de estos, con un problema nuevo cada vez que termina uno.'],
            ]],
        ],
        'questions' => [
            ['topic' => 'math', 'correct' => 0,
                'en' => ['prompt' => 'A home sells for 350,000 dollars with a 3 percent commission. What is the commission?', 'options' => ['10,500 dollars', '1,050 dollars', '105,000 dollars', '3,500 dollars'], 'explanation' => 'Part equals whole times rate: 350,000 times 0.03 is 10,500.'],
                'es' => ['prompt' => 'Una casa se vende en 350,000 dólares con una comisión del 3 por ciento. ¿Cuál es la comisión?', 'options' => ['10,500 dólares', '1,050 dólares', '105,000 dólares', '3,500 dólares'], 'explanation' => 'La parte es igual al total por la tasa: 350,000 por 0.03 es 10,500.']],
            ['topic' => 'math', 'correct' => 1,
                'en' => ['prompt' => 'How many square feet are in an acre?', 'options' => ['36,000', '43,560', '640', '5,280'], 'explanation' => 'An acre is 43,560 square feet; 640 acres make a section.'],
                'es' => ['prompt' => '¿Cuántos pies cuadrados tiene un acre?', 'options' => ['36,000', '43,560', '640', '5,280'], 'explanation' => 'Un acre tiene 43,560 pies cuadrados; 640 acres forman una sección.']],
        ],
    ],
    [
        'key' => 'texas-traps',
        'title' => ['en' => 'Texas portion traps', 'es' => 'Trampas de la parte de Texas'],
        'sections' => [
            ['heading' => ['en' => 'Why the Texas portion trips people up', 'es' => 'Por qué la parte de Texas confunde'], 'lines' => [
                ['en' => 'The Texas portion tests Texas law and TREC rules, which sometimes differ from what you learned for the national portion. When a question says Texas, answer with the Texas rule.', 'es' => 'La parte de Texas evalúa las leyes de Texas y las reglas de TREC, que a veces difieren de lo que aprendió para la parte nacional. Cuando la pregunta dice Texas, responda con la regla de Texas.'],
            ]],
            ['heading' => ['en' => 'Information About Brokerage Services', 'es' => 'Información sobre Servicios de Corretaje'], 'lines' => [
                ['en' => 'The Information About Brokerage Services notice, or IABS, is given at the first substantive communication with a party about a specific property.', 'es' => 'El aviso de Información sobre Servicios de Corretaje, o IABS, se entrega en la primera comunicación sustancial con una parte sobre una propiedad específica.'],
                ['en' => 'It explains the types of representation. It is not a contract, and giving it doesn\'t create an agency relationship.', 'es' => 'Explica los tipos de representación. No es un contrato, y entregarlo no crea una relación de representación.'],
            ]],
            ['heading' => ['en' => 'Intermediary', 'es' => 'Intermediario'], 'lines' => [
                ['en' => 'In Texas, a broker who represents both the buyer and the seller in the same deal acts as an intermediary, and only with the written consent of both parties.', 'es' => 'En Texas, un bróker que representa al comprador y al vendedor en la misma operación actúa como intermediario, y solo con el consentimiento por escrito de ambas partes.'],
                ['en' => 'With that consent, the broker may appoint a different license holder to each party, who may then give that party opinions and advice.', 'es' => 'Con ese consentimiento, el bróker puede designar a un titular de licencia distinto para cada parte, quien entonces puede dar opiniones y consejos a esa parte.'],
                ['en' => 'An intermediary must treat both parties fairly and may not disclose, without permission, that a seller will take less or a buyer will pay more.', 'es' => 'Un intermediario debe tratar a ambas partes con imparcialidad y no puede revelar, sin permiso, que un vendedor aceptaría menos o que un comprador pagaría más.'],
            ]],
            ['heading' => ['en' => 'Your sponsoring broker', 'es' => 'Su bróker patrocinador'], 'lines' => [
                ['en' => 'A sales agent works under a sponsoring broker and is paid only through that broker, never directly by a client or another broker.', 'es' => 'Un agente de ventas trabaja bajo un bróker patrocinador y solo recibe pago a través de ese bróker, nunca directamente de un cliente ni de otro bróker.'],
                ['en' => 'Advertising must include the broker\'s name. An agent\'s name alone is not enough.', 'es' => 'La publicidad debe incluir el nombre del bróker. El nombre del agente por sí solo no es suficiente.'],
            ]],
            ['heading' => ['en' => 'Contracts and the practice of law', 'es' => 'Contratos y el ejercicio de la abogacía'], 'lines' => [
                ['en' => 'License holders use the contract forms TREC promulgates and fill in the blanks. Drafting new terms or giving legal advice is the practice of law, which they may not do.', 'es' => 'Los titulares de licencia usan los formularios de contrato que TREC promulga y llenan los espacios. Redactar cláusulas nuevas o dar asesoría legal es ejercer la abogacía, y no pueden hacerlo.'],
            ]],
            ['heading' => ['en' => 'Other Texas facts that show up', 'es' => 'Otros datos de Texas que aparecen'], 'lines' => [
                ['en' => 'Texas is a community property state, and it gives homesteads strong protection from most creditors.', 'es' => 'Texas es un estado de bienes gananciales y otorga a la vivienda familiar una fuerte protección frente a la mayoría de los acreedores.'],
                ['en' => 'The Real Estate Recovery Trust Account can pay people harmed by a license holder\'s violation of the law, within limits set by law.', 'es' => 'La Cuenta Fiduciaria de Recuperación de Bienes Raíces puede pagar a las personas perjudicadas por la violación de la ley de un titular de licencia, dentro de los límites que fija la ley.'],
                ['en' => 'A new sales agent must complete 98 hours of sales agent apprentice education before the first license renewal.', 'es' => 'Un agente de ventas nuevo debe completar 98 horas de educación de aprendiz de agente de ventas antes de la primera renovación de su licencia.'],
            ]],
        ],
        'questions' => [
            ['topic' => 'state_rules', 'correct' => 2,
                'en' => ['prompt' => 'When is the Information About Brokerage Services notice given?', 'options' => ['When the contract is signed', 'Only if the party asks', 'At the first substantive communication with a party about a specific property', 'At closing'], 'explanation' => 'TREC requires the IABS at the first substantive communication about a specific property.'],
                'es' => ['prompt' => '¿Cuándo se entrega el aviso de Información sobre Servicios de Corretaje?', 'options' => ['Cuando se firma el contrato', 'Solo si la parte lo pide', 'En la primera comunicación sustancial con una parte sobre una propiedad específica', 'En el cierre'], 'explanation' => 'TREC exige el IABS en la primera comunicación sustancial sobre una propiedad específica.']],
            ['topic' => 'state_rules', 'correct' => 3,
                'en' => ['prompt' => 'What does a Texas broker need to act as an intermediary?', 'options' => ['The seller\'s verbal consent', 'TREC\'s approval for each deal', 'Nothing; it is automatic', 'The written consent of both parties'], 'explanation' => 'An intermediary relationship needs written consent from both the buyer and the seller.'],
                'es' => ['prompt' => '¿Qué necesita un bróker de Texas para actuar como intermediario?', 'options' => ['El consentimiento verbal del vendedor', 'La aprobación de TREC para cada operación', 'Nada; es automático', 'El consentimiento por escrito de ambas partes'], 'explanation' => 'Una relación de intermediario necesita el consentimiento por escrito del comprador y del vendedor.']],
        ],
    ],
    [
        'key' => 'last-seven-days',
        'title' => ['en' => 'Your last 7 days', 'es' => 'Sus últimos 7 días'],
        'sections' => [
            ['heading' => ['en' => 'Book when you\'re ready', 'es' => 'Programe cuando esté listo'], 'lines' => [
                ['en' => 'REOS suggests booking your exam when your readiness is at 90 percent on both portions and you have passed two full simulations.', 'es' => 'REOS le sugiere programar su examen cuando su preparación esté en el 90 por ciento en ambas partes y haya aprobado dos simulacros completos.'],
                ['en' => 'Once you have a date, your study plan counts down to it. Here is the shape of the last week.', 'es' => 'Una vez que tenga fecha, su plan de estudio cuenta los días hasta ella. Así se ve la última semana.'],
            ]],
            ['heading' => ['en' => 'Days 7 to 5', 'es' => 'Días 7 a 5'], 'lines' => [
                ['en' => 'Day 7: take a full simulation in Exam mode, then go through every miss with Ask why.', 'es' => 'Día 7: haga un simulacro completo en modo examen y después revise cada error con Preguntar por qué.'],
                ['en' => 'Days 6 and 5: work on your two weakest topics with a fix plan each day. Don\'t try to relearn everything.', 'es' => 'Días 6 y 5: trabaje sus dos temas más débiles con un plan de mejora cada día. No intente volver a aprender todo.'],
            ]],
            ['heading' => ['en' => 'Days 4 to 2', 'es' => 'Días 4 a 2'], 'lines' => [
                ['en' => 'Day 4: take the Texas portion test, then review the Texas traps lesson.', 'es' => 'Día 4: haga la prueba de la parte de Texas y después repase la lección de trampas de la parte de Texas.'],
                ['en' => 'Day 3: the math lab and your flashcards due. Short sessions, fully focused.', 'es' => 'Día 3: el laboratorio de matemáticas y sus tarjetas de estudio pendientes. Sesiones cortas y con toda su atención.'],
                ['en' => 'Day 2: your last full simulation, at the same time of day as your exam if you can.', 'es' => 'Día 2: su último simulacro completo, a la misma hora que su examen si puede.'],
            ]],
            ['heading' => ['en' => 'The day before', 'es' => 'El día anterior'], 'lines' => [
                ['en' => 'Day 1 is light: flashcards and a speed round, nothing new. Stop early.', 'es' => 'El día 1 es ligero: tarjetas de estudio y una ronda rápida, nada nuevo. Termine temprano.'],
                ['en' => 'Check the test center address, how long it takes to get there, and the identification your candidate handbook asks for.', 'es' => 'Confirme la dirección del centro de examen, cuánto tarda en llegar y la identificación que pide su manual del candidato.'],
                ['en' => 'Plan to arrive at least 30 minutes early, and get a full night\'s sleep.', 'es' => 'Planee llegar al menos 30 minutos antes y duerma toda la noche.'],
            ]],
        ],
        'questions' => [
            ['topic' => 'state_rules', 'correct' => 0,
                'en' => ['prompt' => 'What does REOS suggest before you book your exam?', 'options' => ['90 percent readiness on both portions and two passed simulations', 'Finishing every flashcard', 'One diagnostic test', '70 percent on the national portion only'], 'explanation' => 'The booking line is 90 percent on both portions, with two full simulations passed.'],
                'es' => ['prompt' => '¿Qué sugiere REOS antes de programar su examen?', 'options' => ['El 90 por ciento de preparación en ambas partes y dos simulacros aprobados', 'Terminar todas las tarjetas de estudio', 'Una prueba de diagnóstico', 'El 70 por ciento solo en la parte nacional'], 'explanation' => 'La línea para programar es el 90 por ciento en ambas partes, con dos simulacros completos aprobados.']],
            ['topic' => 'state_rules', 'correct' => 1,
                'en' => ['prompt' => 'What is the best plan for the day before the exam?', 'options' => ['No sleep, more study', 'Light review, check the logistics and sleep', 'A full simulation late at night', 'Learning a new topic'], 'explanation' => 'The last day is for light review, checking the center and your ID, and a full night\'s sleep.'],
                'es' => ['prompt' => '¿Cuál es el mejor plan para el día anterior al examen?', 'options' => ['No dormir y estudiar más', 'Repaso ligero, confirmar la logística y dormir', 'Un simulacro completo de noche', 'Aprender un tema nuevo'], 'explanation' => 'El último día es para un repaso ligero, confirmar el centro y su identificación, y dormir toda la noche.']],
        ],
    ],
    [
        'key' => 'exam-day',
        'title' => ['en' => 'Exam day and nerves', 'es' => 'El día del examen y los nervios'],
        'sections' => [
            ['heading' => ['en' => 'Nerves are normal', 'es' => 'Los nervios son normales'], 'lines' => [
                ['en' => 'Almost everyone feels nervous on exam day. A little nervousness keeps you alert; the goal is to keep it from taking over.', 'es' => 'Casi todas las personas sienten nervios el día del examen. Un poco de nervios le mantiene alerta; la meta es que no tomen el control.'],
            ]],
            ['heading' => ['en' => 'Before you start', 'es' => 'Antes de empezar'], 'lines' => [
                ['en' => 'Eat something light, bring only what the center allows, and use the tutorial at the start to get used to the screen.', 'es' => 'Coma algo ligero, lleve solo lo que el centro permite y use el tutorial del inicio para acostumbrarse a la pantalla.'],
                ['en' => 'Take a slow breath: in for four counts, hold for a moment, out for six. Do it two or three times.', 'es' => 'Respire despacio: inhale contando hasta cuatro, sostenga un momento y exhale contando hasta seis. Hágalo dos o tres veces.'],
            ]],
            ['heading' => ['en' => 'When a question shakes you', 'es' => 'Cuando una pregunta le desconcierta'], 'lines' => [
                ['en' => 'You will meet questions you don\'t know. Some are trial questions that don\'t count. Pick your best answer, flag it and move on.', 'es' => 'Encontrará preguntas que no sabe. Algunas son de prueba y no cuentan. Elija su mejor respuesta, márquela y siga adelante.'],
                ['en' => 'Don\'t let one hard question cost you the next three. Each question is a fresh start.', 'es' => 'No deje que una pregunta difícil le cueste las tres siguientes. Cada pregunta es un nuevo comienzo.'],
                ['en' => 'If your mind goes blank, take your hands off the keyboard, breathe once, and read the question again slowly.', 'es' => 'Si se queda en blanco, retire las manos del teclado, respire una vez y vuelva a leer la pregunta despacio.'],
            ]],
            ['heading' => ['en' => 'After the exam', 'es' => 'Después del examen'], 'lines' => [
                ['en' => 'You get your score report at the test center. If you passed, your next steps toward your license are in REOS.', 'es' => 'Recibe su informe de calificación en el centro de examen. Si aprobó, sus siguientes pasos hacia su licencia están en REOS.'],
                ['en' => 'If you didn\'t pass a portion, the report shows how you did by topic. Keep studying in REOS, where your plan keeps working on your weakest topics, and retake only that portion.', 'es' => 'Si no aprobó una parte, el informe muestra cómo le fue por tema. Siga estudiando en REOS, donde su plan sigue trabajando sus temas más débiles, y vuelva a presentar solo esa parte.'],
                ['en' => 'Many good agents needed more than one try. What matters is what you do next.', 'es' => 'Muchos buenos agentes necesitaron más de un intento. Lo que importa es lo que haga después.'],
            ]],
        ],
        'questions' => [
            ['topic' => 'state_rules', 'correct' => 2,
                'en' => ['prompt' => 'You meet a question you don\'t know at all. What should you do?', 'options' => ['Spend five minutes on it', 'Stop the exam', 'Pick your best answer, flag it and move on', 'Leave it blank'], 'explanation' => 'Some questions are trial questions that don\'t count; answer, flag and keep your pace.'],
                'es' => ['prompt' => 'Encuentra una pregunta que no sabe en absoluto. ¿Qué debe hacer?', 'options' => ['Dedicarle cinco minutos', 'Detener el examen', 'Elegir su mejor respuesta, marcarla y seguir', 'Dejarla en blanco'], 'explanation' => 'Algunas preguntas son de prueba y no cuentan; responda, marque y mantenga su ritmo.']],
            ['topic' => 'state_rules', 'correct' => 3,
                'en' => ['prompt' => 'Which breathing pattern does the lesson suggest to calm nerves?', 'options' => ['Hold your breath as long as you can', 'Breathe fast for a minute', 'Breathing doesn\'t help', 'In for four counts, a short hold, out for six'], 'explanation' => 'A slow breath out, longer than the breath in, helps you settle.'],
                'es' => ['prompt' => '¿Qué forma de respirar sugiere la lección para calmar los nervios?', 'options' => ['Contener la respiración lo más posible', 'Respirar rápido durante un minuto', 'Respirar no ayuda', 'Inhalar contando hasta cuatro, sostener un momento y exhalar contando hasta seis'], 'explanation' => 'Exhalar despacio, más tiempo del que inhala, le ayuda a calmarse.']],
        ],
    ],
];
