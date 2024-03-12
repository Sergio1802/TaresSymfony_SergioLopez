<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use App\Form\RegistrationFormType;
use App\Form\LoginFormType;
use App\Entity\Usuario;
use App\Entity\Tarea;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;



class Controlador extends AbstractController
{
    #[Route('/', name: 'index')]
    public function index(): Response
    {
        $loginForm = $this->createForm(LoginFormType::class);

        return $this->render('index.html.twig', [
            'loginForm' => $loginForm->createView(),
        ]);
    }

    #[Route('/pagina_registro', name: 'ruta_registro')]
    public function irPaginaRegistro(Request $request): Response
    {
        $form = $this->createForm(RegistrationFormType::class);
        $form->handleRequest($request);

        return $this->render('registro.html.twig', [
            'registrationForm' => $form->createView(),
        ]);
    }

    #[Route('/registro', name: 'registro')]
    public function registro(Request $request, EntityManagerInterface $entityManager, UserPasswordHasherInterface $passwordHasher): Response
    {
        $form = $this->createForm(RegistrationFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user = $form->getData();

            $hashedPassword = $passwordHasher->hashPassword($user, $user->getPassword());
            $user->setPassword($hashedPassword);

            $entityManager->persist($user);
            $entityManager->flush();

            return $this->redirectToRoute('index');
        }

        return $this->render('registro.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/iniciar_sesion', name: 'inicio_sesion')]
    public function iniciarSesion(Request $request, UserPasswordHasherInterface $passwordHasher, EntityManagerInterface $entityManager, SessionInterface $session): Response
    {
        $loginForm = $this->createForm(LoginFormType::class);
        $loginForm->handleRequest($request);

        if ($loginForm->isSubmitted() && $loginForm->isValid()) {
            $data = $loginForm->getData();

            $user = $entityManager->getRepository(Usuario::class)->findOneBy(['email' => $data['email']]);

            if (!$user || !$passwordHasher->isPasswordValid($user, $data['password'])) {
                $error = 'Credenciales inválidas';
                return $this->render('index.html.twig', [
                    'loginForm' => $loginForm->createView(),
                    'error' => $error,
                ]);
            }

            $session->set('user_id', $user->getId());

            return $this->redirectToRoute('inicio');
        }

        return $this->render('index.html.twig', [
            'loginForm' => $loginForm->createView(),
        ]);
    }

    #[Route('/inicio', name: 'inicio')]
    public function inicio(EntityManagerInterface $entityManager, Request $request, SessionInterface $session): Response
    {
        $userId = $session->get('user_id');

        $user = $entityManager->getRepository(Usuario::class)->find($userId);

        if (!$user) {
            throw $this->createNotFoundException('Usuario no encontrado');
        }

        $tareas = $entityManager->getRepository(Tarea::class)->findBy(['usuario' => $user]);

        return $this->render('inicio.html.twig', [
            'tareas' => $tareas,
            'usuario' => $user,
        ]);
    }
    #[Route('/crear-tarea-ajax', name: 'crear_tarea_ajax', methods: ['POST'])]
    public function creartareaAjax(Request $request, EntityManagerInterface $entityManager, SessionInterface $session): JsonResponse
    {
        $jsonData = json_decode($request->getContent(), true);
        $texto = $jsonData['texto'];
        $fecha = new \DateTime(); 
        $realizada = 0; 

        $userId = $session->get('user_id');
        $user = $entityManager->getRepository(Usuario::class)->find($userId);

        $tarea = new Tarea();
        $tarea->setTexto($texto);
        $tarea->setFecha($fecha);
        $tarea->setRealizada($realizada);
        $tarea->setUsuario($user);

        $entityManager->persist($tarea);
        $entityManager->flush();

        return new JsonResponse(['mensaje' => 'Tarea creada correctamente', 'texto' => $tarea->getTexto(), 'id' => $tarea->getId()], JsonResponse::HTTP_CREATED);
    }
    #[Route('/eliminar-tarea-ajax', name: 'eliminar_tarea_ajax', methods: ['POST'])]
    public function eliminarTareaAjax(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $jsonData = json_decode($request->getContent(), true);
        $id = $jsonData['id'];

        $tarea = $entityManager->getRepository(Tarea::class)->find($id);

        if (!$tarea) {
            return new JsonResponse(['mensaje' => 'La tarea no existe'], JsonResponse::HTTP_NOT_FOUND);
        }

        $entityManager->remove($tarea);
        $entityManager->flush();

        return new JsonResponse(['mensaje' => 'Tarea eliminada correctamente'], JsonResponse::HTTP_OK);
    }

    #[Route('/marcar-tarea-ajax', name: 'marcar_tarea_ajax', methods: ['POST'])]
public function marcarTareaAjax(Request $request, EntityManagerInterface $entityManager): JsonResponse
{
    $jsonData = json_decode($request->getContent(), true);
    $id = $jsonData['id'];

    $tarea = $entityManager->getRepository(Tarea::class)->find($id);

    if (!$tarea) {
        return new JsonResponse(['mensaje' => 'La tarea no existe'], JsonResponse::HTTP_NOT_FOUND);
    }

    $tarea->setRealizada($tarea->getRealizada() === 1 ? 0 : 1);

    $entityManager->flush();

    return new JsonResponse(['mensaje' => 'Estado de tarea actualizado correctamente'], JsonResponse::HTTP_OK);
}

    #[Route('/cerrar_sesion', name: 'cerrar_sesion')]
    public function cerrarSesion(SessionInterface $session): RedirectResponse
    {
        $session->invalidate();

        return $this->redirectToRoute('index');
    }
}
