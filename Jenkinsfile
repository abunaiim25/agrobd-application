pipeline {
    agent any

    environment {
        IMAGE_TAG = "${BUILD_NUMBER}"
    }

    stages {

        stage('Checkout App Repo'){
            steps {
                git branch: 'main',
                credentialsId: 'jenkins-github-https-cred',
                url: 'https://github.com/abunaiim25/agrobd-application.git'
            }
        }

        stage('Build Docker Image'){
            steps {
                script {
                    sh '''
                    echo 'Building Docker Image...'
                    docker build -t mdnaiim/agrobd-app:${BUILD_NUMBER} .
                    '''
                }
            }
        }

        stage('Push Docker Image'){
            steps {
                withCredentials([usernamePassword(
                    credentialsId: 'dockerhub-cred',
                    passwordVariable: 'DOCKER_PASSWORD',
                    usernameVariable: 'DOCKER_USERNAME')])
                    {
                    sh '''
                    echo "$DOCKER_PASSWORD" | docker login -u "$DOCKER_USERNAME" --password-stdin
                    docker push mdnaiim/agrobd-app:${BUILD_NUMBER}
                    '''
                }
            }
        }

        stage('Checkout K8S Manifest Repo'){
            steps {
                git branch: 'main',
                credentialsId: 'jenkins-github-https-cred',
                url: 'https://github.com/abunaiim25/AgroBd-DEPLOYMENT.git'
            }
        }

        stage('Update Manifest & Push'){
            steps {
                script {
                    withCredentials([usernamePassword(
                        credentialsId: 'jenkins-github-https-cred',
                        passwordVariable: 'GIT_PASSWORD',
                        usernameVariable: 'GIT_USERNAME')])
                        {
                        sh '''
                        sed -i "s/32/${BUILD_NUMBER}/g" deployment.yaml
                        git add deployment.yaml
                        git commit -m "Updated image tag to ${BUILD_NUMBER}"
                        git push https://${GIT_USERNAME}:${GIT_PASSWORD}@github.com/abunaiim25/AgroBd-DEPLOYMENT.git HEAD:main
                        '''
                    }
                }
            }
        }
    }
}
