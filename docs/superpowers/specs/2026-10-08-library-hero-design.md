# Spécification de design — Hero du site de bibliothèque

**Date :** 2026-10-08  
**Périmètre :** section Hero uniquement, première étape du site vitrine de l’application de réservation de livres.

## Objectif

Présenter immédiatement la bibliothèque et son service de réservation, avec une ambiance éditoriale chaleureuse et une interface minimaliste. Le Hero doit orienter vers la découverte des livres et l’accès à l’application.

## Direction visuelle

- Fond ivoire `#F7F4ED`, vert profond `#24483F`, accent terre cuite `#C87552`, texte anthracite.
- Titres en serif élégante ; textes et contrôles en sans-serif lisible.
- Composition aérée : titre et description lisibles sur la vidéo, appels à l’action clairement hiérarchisés.
- Vidéo plein écran/couverture en arrière-plan, avec voile dégradé sombre/vert pour préserver le contraste. La vidéo reste décorative et muette.

## Contenu et interaction du slider

Le même arrière-plan vidéo reste continu pendant que trois messages défilent :

1. **La bibliothèque, à portée de clic.** — « Découvrez les services de votre bibliothèque et explorez ses collections. »
2. **Trouvez votre prochaine lecture.** — « Parcourez une sélection de livres et trouvez celui qui vous ressemble. »
3. **Réservez en quelques instants.** — « Choisissez un livre et retrouvez-le facilement grâce à l’application. »

Chaque diapositive propose les actions « Découvrir les livres » et « Accéder à l’application ». Les destinations seront configurables ; aucune URL d’application n’est connue à ce stade.

- Transition douce par fondu, avec léger déplacement vertical du texte ; durée indicative de 500–700 ms.
- Défilement automatique toutes les 6 secondes, avec commandes précédent/suivant et indicateur de progression ou de position.
- Les commandes restent discrètes mais accessibles au clavier ; le focus ou une interaction utilisateur suspend le défilement automatique.
- Respect de `prefers-reduced-motion` : supprimer le déplacement et l’automatisme, et afficher une diapositive statique ou laisser le changement manuel.

## Média et adaptation

- Utiliser la vidéo MP4 déjà présente dans l’espace comme média candidat, après vérification visuelle de son adéquation.
- Lire la vidéo en muet, en boucle et en lecture intégrée sur mobile ; si l’autoplay est refusé, si la vidéo échoue ou si l’appareil préfère réduire les animations, montrer une image fixe de remplacement.
- Recadrer la vidéo sans déformer son ratio (`cover`) et adapter le positionnement du texte aux petits écrans.
- Prévoir une image d’affiche légère et une couleur de fond avant chargement pour éviter un écran vide.

## Accessibilité et critères de validation

- Contraste suffisant entre le texte, les CTA et la vidéo, y compris pendant les images les plus claires.
- Les transitions et le slider ne doivent pas déplacer le focus ; les boutons ont des libellés explicites et des états focus visibles.
- Le contenu reste compréhensible sans vidéo, sans animation, sur mobile et lorsque le média échoue.
- Vérifier le fonctionnement des commandes, le changement des trois diapositives, les liens configurables et le comportement de réduction du mouvement.

## Hors périmètre immédiat

Catalogue complet, pages de services, authentification/réservation, intégration réelle avec l’application et choix de framework. Ces éléments seront cadrés à part.
